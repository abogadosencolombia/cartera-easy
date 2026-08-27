<?php

namespace App\Console\Commands;

use App\Services\CrearcoopSmsCampaignPlanner;
use App\Services\RneProofVerifier;
use App\Services\SmsLegalWindow;
use App\Services\SmsSuppressionService;
use App\Services\TwilioCampaignService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

class SendTwilioCampaign extends Command
{
    protected $signature = 'app:send-twilio-campaign
                            {manifest : Archivo JSON inmutable dentro del directorio privado configurado}
                            {--dry-run : Evalúa el lote sin persistir campaña ni contactar Twilio}
                            {--send : Sella y ejecuta los destinatarios elegibles}
                            {--at= : Fecha/hora objetivo YYYY-MM-DD HH:MM:SS en Bogotá}
                            {--timezone=America/Bogota : Debe ser America/Bogota}
                            {--rne-proof= : Constancia JSON del cruce CRC RNE del mismo día}
                            {--confirm-manifest= : SHA-256 exacto requerido para --send}
                            {--confirm-plan= : SHA-256 exacto del plan dinámico requerido para --send}
                            {--delay-seconds=60 : Pausa entre envíos; cero sólo se permite en tests}';

    protected $description = 'Previsualiza o ejecuta de forma auditable el manifiesto cerrado de 44 SMS CREARCOOP.';

    public function handle(
        CrearcoopSmsCampaignPlanner $planner,
        TwilioCampaignService $campaigns,
        SmsSuppressionService $suppressions,
        RneProofVerifier $rneProofs,
        SmsLegalWindow $legalWindow,
    ): int {
        $isDryRun = (bool) $this->option('dry-run');
        $isSend = (bool) $this->option('send');

        if ($isDryRun === $isSend) {
            $this->error('Seleccione exactamente uno: --dry-run o --send.');

            return self::FAILURE;
        }

        try {
            $manifestPath = $this->resolvePrivateJson(
                (string) $this->argument('manifest'),
                (string) config('services.twilio.campaign_manifest_root'),
                'manifiesto',
            );
            $manifestSha256 = hash_file('sha256', $manifestPath);
            $manifest = $this->readJson($manifestPath, 'manifiesto');
            $scheduledAt = $this->scheduledAt();
            $rneProof = $this->loadRneProof();
            $plan = $planner->plan($manifest, $manifestSha256, $scheduledAt, $rneProof);
        } catch (DomainException|JsonException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            Log::error('Falló el preflight de campaña Twilio.', ['exception' => $exception::class]);
            $this->error('No fue posible completar el preflight de manera segura.');

            return self::FAILURE;
        }

        $this->renderPlan($plan);

        if ($isDryRun) {
            $this->info('DRY-RUN: no se persistió una campaña y no se llamó a Twilio.');

            return self::SUCCESS;
        }

        if ($rneProof === null) {
            $this->error('El envío exige una constancia RNE completa del mismo día.');

            return self::FAILURE;
        }

        $confirmation = strtolower(trim((string) $this->option('confirm-manifest')));

        if (! preg_match('/^[0-9a-f]{64}$/', $confirmation)
            || ! hash_equals($manifestSha256, $confirmation)) {
            $this->error('La confirmación SHA-256 no coincide con el manifiesto inmutable.');

            return self::FAILURE;
        }

        $planConfirmation = strtolower(trim((string) $this->option('confirm-plan')));

        if (! preg_match('/^[0-9a-f]{64}$/', $planConfirmation)
            || ! hash_equals((string) $plan['plan_sha256'], $planConfirmation)) {
            $this->error('La confirmación SHA-256 no coincide con el plan dinámico vigente.');

            return self::FAILURE;
        }

        $now = CarbonImmutable::now(SmsLegalWindow::TIMEZONE);

        if ($now->lessThan($scheduledAt) || $now->greaterThanOrEqualTo($scheduledAt->addMinutes(5))) {
            $this->error('El modo --send solo se habilita durante los primeros cinco minutos de la ventana aprobada.');

            return self::FAILURE;
        }

        if ((int) $plan['included'] < 1) {
            $this->error('El preflight no dejó destinatarios elegibles; no se selló ni envió la campaña.');

            return self::FAILURE;
        }

        $delay = filter_var($this->option('delay-seconds'), FILTER_VALIDATE_INT);

        if (! is_int($delay) || $delay < 0 || $delay > 300 || ($delay === 0 && ! app()->environment('testing'))) {
            $this->error('La pausa debe estar entre 1 y 300 segundos; cero solo se admite en tests.');

            return self::FAILURE;
        }

        try {
            $finalManifest = $this->applyDecisions($manifest, $plan['recipient_decisions']);
            $campaign = $campaigns->createFromManifest($finalManifest, [
                'raw_manifest_sha256' => $manifestSha256,
                'rne_proof_sha256' => $plan['rne_proof_sha256'],
                'plan_sha256' => $plan['plan_sha256'],
                'rne_checked_at' => $rneProof['checked_at'],
                'scheduled_at' => $scheduledAt->toIso8601String(),
                'included_count' => $plan['included'],
            ]);
            $ready = $campaign->recipients()->where('status', 'ready')->orderBy('ordinal')->get();
            $sourceByOrdinal = collect($manifest['recipients'])->keyBy('ordinal');
            $proofStatuses = $rneProofs->verify(
                $rneProof,
                (string) $manifest['campaign_key'],
                $manifestSha256,
                $scheduledAt,
                array_values(array_filter(array_column($manifest['recipients'], 'phone_fingerprint'))),
            );
            $submitted = 0;

            foreach ($ready as $position => $recipient) {
                $source = $sourceByOrdinal->get($recipient->ordinal);

                if (! is_array($source)) {
                    throw new DomainException('No se pudo reconciliar un destinatario con el manifiesto sellado.');
                }

                $current = CarbonImmutable::now(SmsLegalWindow::TIMEZONE);
                $lastContact = $legalWindow->parseContactAt($source['last_contact_at'] ?? null);
                $legal = $legalWindow->evaluate($current, $lastContact);

                if (! $legal['allowed']
                    || $suppressions->isSuppressed((string) $recipient->phone)
                    || ($proofStatuses[$source['phone_fingerprint']] ?? null) !== 'clear') {
                    throw new DomainException('Un destinatario dejó de ser elegible durante la ejecución; campaña detenida.');
                }

                $campaigns->sendRecipient($recipient);
                $submitted++;

                if ($delay > 0 && $position < $ready->count() - 1) {
                    sleep($delay);
                }
            }

            $this->line(json_encode(['submitted' => $submitted], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $this->info('Campaña sometida a Twilio; los estados finales llegarán por callback.');

            return self::SUCCESS;
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            Log::error('Campaña Twilio detenida durante la ejecución.', [
                'campaign_key' => $manifest['campaign_key'] ?? null,
                'exception' => $exception::class,
            ]);
            $this->error('La campaña se detuvo de forma segura. No se harán reintentos automáticos.');

            return self::FAILURE;
        }
    }

    private function scheduledAt(): CarbonImmutable
    {
        $timezone = (string) $this->option('timezone');
        $value = trim((string) $this->option('at'));

        if ($timezone !== SmsLegalWindow::TIMEZONE || $value === '') {
            throw new DomainException('Use --timezone=America/Bogota y especifique --at.');
        }

        try {
            $scheduledAt = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $value, $timezone);
        } catch (Throwable) {
            throw new DomainException('La fecha --at debe usar YYYY-MM-DD HH:MM:SS.');
        }

        if ($scheduledAt === false || $scheduledAt->format('Y-m-d H:i:s') !== $value) {
            throw new DomainException('La fecha --at debe usar YYYY-MM-DD HH:MM:SS.');
        }

        if ($scheduledAt->format('Y-m-d') !== '2026-08-24') {
            throw new DomainException('Esta autorización técnica está limitada al lunes 24 de agosto de 2026.');
        }

        return $scheduledAt;
    }

    /** @return array<string, mixed>|null */
    private function loadRneProof(): ?array
    {
        $option = trim((string) $this->option('rne-proof'));

        if ($option === '') {
            return null;
        }

        $path = $this->resolvePrivateJson(
            $option,
            (string) config('services.twilio.rne_proof_root'),
            'constancia RNE',
        );

        return $this->readJson($path, 'constancia RNE');
    }

    private function resolvePrivateJson(string $input, string $root, string $label): string
    {
        $rootPath = realpath($root);

        if ($rootPath === false) {
            throw new DomainException("El directorio privado de {$label} no existe.");
        }

        $candidate = str_starts_with($input, DIRECTORY_SEPARATOR) ? $input : $rootPath.DIRECTORY_SEPARATOR.$input;
        $path = realpath($candidate);

        if ($path === false
            || ! str_starts_with($path, $rootPath.DIRECTORY_SEPARATOR)
            || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'json'
            || ! is_file($path)
            || (fileperms($path) & 0222) !== 0) {
            throw new DomainException("El archivo de {$label} debe ser un JSON inmutable dentro del directorio privado.");
        }

        return $path;
    }

    /** @return array<string, mixed> */
    private function readJson(string $path, string $label): array
    {
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new DomainException("El archivo de {$label} no contiene un objeto JSON.");
        }

        return $decoded;
    }

    /** @param array<string, mixed> $plan */
    private function renderPlan(array $plan): void
    {
        unset($plan['recipient_decisions']);
        $this->line(json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  list<array<string, mixed>>  $decisions
     * @return array<string, mixed>
     */
    private function applyDecisions(array $manifest, array $decisions): array
    {
        $byOrdinal = collect($decisions)->keyBy('ordinal');

        foreach ($manifest['recipients'] as &$recipient) {
            $decision = $byOrdinal->get($recipient['ordinal']);

            if (! is_array($decision)) {
                throw new DomainException('El plan no cubre los 44 destinatarios del manifiesto.');
            }

            $recipient['status'] = $decision['status'] === 'included' ? 'ready' : 'excluded';
            $recipient['exclusion_reason'] = $decision['status'] === 'included'
                ? null
                : implode('|', $decision['reasons']);
        }
        unset($recipient);

        return $manifest;
    }
}

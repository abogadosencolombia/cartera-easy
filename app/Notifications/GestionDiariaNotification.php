<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\NotaGestion;

class GestionDiariaNotification extends Notification
{
    // No usamos ShouldQueue por el problema de las colas que arreglamos antes
    
    protected $nota;
    protected $tipo; // "proxima" (faltando 1h) o "vencida"

    public function __construct(NotaGestion $nota, string $tipo)
    {
        $this->nota = $nota;
        $this->tipo = $tipo;
    }

    public function via($notifiable): array
    {
        return ["database", "mail"];
    }

    public function toMail($notifiable): MailMessage
    {
        $titulo = $this->tipo === "proxima" ? "⚠️ Tarea por vencer (1 hora)" : "🚨 Tarea VENCIDA";
        
        return (new MailMessage)
                    ->subject($titulo . ": " . $this->nota->descripcion)
                    ->greeting("Hola, " . $notifiable->name)
                    ->line($this->getMensaje())
                    ->line("Detalle: " . $this->nota->descripcion)
                    ->line("Entidad: " . $this->nota->despacho)
                    ->action("Ver Hoja de Ruta", url("/dashboard"))
                    ->line("Por favor, gestione esta tarea lo antes posible.")
                    ->level($this->tipo === "vencida" ? "error" : "warning");
    }

    public function toArray($notifiable): array
    {
        return [
            "nota_id" => $this->nota->id,
            "titulo"  => $this->tipo === "proxima" ? "Termino por vencer" : "Termino Vencido",
            "message" => $this->getMensaje(),
            "link"    => "/dashboard",
            "tipo"    => "gestion_diaria",
            "estado"  => $this->tipo,
        ];
    }

    protected function getMensaje(): string
    {
        if ($this->tipo === "proxima") {
            return "La gestión '{$this->nota->descripcion}' vencerá en menos de 1 hora.";
        }
        return "La gestión '{$this->nota->descripcion}' se encuentra vencida. Por favor complétela o elimínela.";
    }
}

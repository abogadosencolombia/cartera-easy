# Registro de Cambios - UI/UX y Componentes de Fecha

Este documento detalla las modificaciones realizadas el 16 de junio de 2026 para mejorar la interfaz de usuario en el módulo de Radicados y solucionar problemas de visualización en componentes globales.

---

## 1. Rediseño del Modal "Avanzar Etapa"
**Archivo modificado:** `resources/js/Pages/Radicados/Show.vue`

### Mejoras Estéticas:
- **Encabezado Enriquecido:** Se añadió un icono `ArrowPathIcon` con fondo índigo suave para dar identidad visual al modal.
- **Tipografía y Espaciado:** Se ajustaron los labels a un estilo de fuente negra, mayúsculas y tracking ancho (`tracking-widest`) para consistencia con el resto del dashboard.
- **Botones de Acción:** Se rediseñaron los botones para que ocupen el ancho completo en formato columna, mejorando la usabilidad en dispositivos móviles y dando un aspecto más moderno.
- **Modo Oscuro:** Se añadieron clases `dark:*` para asegurar que el modal se vea correctamente en el tema oscuro del sistema.

### Cambios Funcionales:
- **Limpieza de Nombres:** Se eliminó la visualización del riesgo que aparecía entre paréntesis al lado del nombre de cada etapa en la lista desplegable (ej. de "ETAPA (ALTO)" a solo "ETAPA").

---

## 2. Solución al Conflicto de Capas (Z-Index / Stacking Context)
**Archivos modificados:**
- `resources/js/Components/DatePicker.vue`
- `resources/js/Components/DateTimePicker.vue`

### El Problema:
El componente `Modal.vue` del proyecto utiliza un `z-index: 10000`. Los calendarios de selección de fecha se renderizaban dentro del modal con un `z-index` inferior o quedaban recortados por contenedores con `overflow: hidden`.

### La Solución:
Se implementó un patrón de **Posicionamiento Flotante con Teleport**:

1.  **Aumento de Z-Index:** El panel del calendario ahora utiliza `z-[11000]`, asegurando que siempre esté por encima del fondo y el contenido del modal.
2.  **Uso de Teleport:** Se añadió la etiqueta `<Teleport to="body">` (configurable mediante la prop `teleport`). Esto permite que el DOM del calendario se renderice directamente bajo el `<body>`, rompiendo cualquier restricción de `overflow` del contenedor padre.
3.  **Lógica de Posicionamiento Dinámico:** 
    - Se integró `@vueuse/core` (`useElementBounding` y `useWindowSize`).
    - Se creó un objeto reactivo `floatingStyles` que calcula en tiempo real la posición `top` y `left` del calendario basándose en la ubicación del input en pantalla.
    - Se añadió detección automática para abrir el calendario hacia arriba si no hay suficiente espacio en la parte inferior de la ventana.

### Nueva Propiedad Disponible:
Ambos componentes ahora aceptan la propiedad `teleport`:
- **Tipo:** `String`, `Boolean` u `Object`.
- **Default:** `'body'`.
- **Uso:** `<DatePicker v-model="..." teleport="body" />`

---

## 3. Guía de Uso para Desarrolladores

Para implementar selectores de fecha dentro de modales sin problemas de visibilidad, use la sintaxis estándar. Los cambios son retrocompatibles y mejoran la experiencia por defecto:

```vue
<!-- En cualquier vista o modal -->
<DatePicker 
    v-model="fecha" 
    teleport="body" 
    class="w-full" 
/>

<DateTimePicker 
    v-model="fechaHora" 
    teleport="body" 
    class="w-full" 
/>
```

---
*Fin de la documentación.*

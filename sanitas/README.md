# Landing Page — Policlínica Sanitas (Lambaré, Paraguay)

Página única, estática y autocontenida: `sanitas/index.html`.
No requiere build, dependencias ni servidor: se puede subir tal cual a
cualquier hosting o CDN.

## Qué incluye

| Sección | Contenido |
|---|---|
| Header sticky | Logo, menú ancla, CTA «Agendar consulta», glassmorphism y reducción de altura al hacer scroll |
| Hero | Titular, subtítulo, CTA de WhatsApp + «Ver especialidades» y composición 3D (corazón, anillos orbitales, tarjeta de ECG y cards flotantes) |
| Barra de confianza | Atención integral · Cuidado familiar · En Lambaré · Atención a domicilio |
| Sobre Sanitas | Texto institucional + foto del lugar y cards flotantes |
| Conocé la policlínica | Galería de 5 fotos del local en grilla asimétrica |
| Especialidades | 7 especialidades + tarjeta CTA de orientación |
| Estudios y procedimientos | 6 servicios + escena 3D de cardiología |
| Laboratorio | Franja oscura con 4 cards de vidrio y CTA |
| Servicios más solicitados | Electrocardiograma · PAP · Atención psicológica infantil |
| Etapas de la vida | Niños · Adultos · Adultos mayores · Familias |
| Motivos de consulta | 9 accesos directos a WhatsApp con mensaje contextual |
| Atención psicológica | Sección dedicada con tags y CTA |
| Atención a domicilio | Ilustración 3D de casa + aclaración de disponibilidad por zona |
| Equipo profesional | Bloque institucional **sin datos inventados**; se convierte en grilla de profesionales al completar un array |
| Ubicación | Datos de contacto, mapa de Google diferido, botón «Cómo llegar» y nota de cobertura |
| CTA final | Franja azul profunda con el número de WhatsApp |
| Footer | Datos, enlaces y aviso de derechos |
| Flotantes | Botón de WhatsApp en desktop y barra fija inferior en mobile |

## WhatsApp

Todos los CTA abren `https://wa.me/595982420735` con un mensaje precargado
distinto según la sección (especialidad, laboratorio, domicilio, etc.).
Para cambiar el número, reemplazá `595982420735` en todo el archivo.

## Identidad

Rojo Sanitas sobre blanco. La paleta vive en el bloque `:root` de
`index.html`: `--red-600` (#C41E33) es el color de marca, `--red-900` y
`--red-950` sostienen las franjas oscuras, y los neutros están sesgados hacia
el rojo para que nada se vea gris de catálogo. El grafito (`--graphite`)
aporta variedad sin ensuciar la marca.

El verde aparece en dos lugares y sólo con una función: los tildes de
confirmación (`--ok`) y el botón flotante de WhatsApp, que se deja verde
porque es el color con el que la gente reconoce ese canal. Todos los demás
CTA son rojo de marca. Para volver a tener los CTA en verde, cambiá
`btn--brand` por `btn--wa` en los botones.

## Fotografías

Las imágenes se cargan solas: cada lugar tiene un `<img>` apuntando a
`fotos/…` y, detrás, una composición de marca. Si el archivo todavía no
existe, el `<img>` se retira solo y queda la composición — la página nunca
muestra un ícono roto.

Los nombres de archivo, qué mostrar en cada uno y los tamaños sugeridos están
en **`fotos/README.md`**. Son 10 imágenes: 5 de la galería «Conocé la
policlínica», 1 de la sección «Sobre Sanitas» y 4 de «Etapas de la vida».

**No usar bancos de imágenes** para pasar fotos de desconocidos por el local o
por el equipo.

## Profesionales

No se cargó ningún nombre, retrato ni registro profesional porque esa
información no fue provista. La sección muestra un bloque institucional y, en
cuanto se complete el array `SANITAS_PROFESIONALES` de `index.html`, lo
reemplaza por una grilla de tarjetas con foto, nombre, especialidad, registro,
formación y un WhatsApp que ya menciona a ese profesional:

```js
window.SANITAS_PROFESIONALES = [
  { foto:'fotos/equipo/ana-gimenez.jpg', nombre:'Dra. Ana Giménez',
    especialidad:'Ginecología y Obstetricia', registro:'Reg. Prof. 12345',
    formacion:'Universidad Nacional de Asunción' }
];
```

**No completar con datos inventados.**

## Mapa

El mapa se inserta con una consulta por dirección (`maps.google.com/maps?q=…`),
sin coordenadas inventadas y sin API key. Se carga recién cuando el usuario se
acerca a la sección, para no penalizar el LCP.

Cuando se confirme la geolocalización exacta, reemplazar el valor de `f.src` en
el script por el embed con coordenadas y actualizar también el destino del
botón «Cómo llegar».

## Detalles técnicos

- HTML + CSS + JS vanilla en un solo archivo; **cero dependencias de runtime**.
- Íconos SVG inline (sprite `<symbol>`): sin peticiones a CDN de íconos.
- Única petición externa: la hoja de fuentes de Google (Manrope + Inter),
  cargada de forma asíncrona con fallback en `<noscript>`.
- Animaciones con IntersectionObserver, parallax 3D con el mouse sólo en
  punteros finos y respeto completo de `prefers-reduced-motion`.
- Mobile first: barra fija de WhatsApp, botones de 54–62 px de alto y sin
  desplazamiento horizontal.
- SEO: title y meta description locales, Open Graph, canonical y datos
  estructurados `MedicalClinic` (sólo con información real provista).
- Accesibilidad: contraste alto, foco visible, `aria-label` en controles,
  menú con `aria-expanded` y cierre con `Escape`.

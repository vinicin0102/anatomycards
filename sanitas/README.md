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
| Sobre Sanitas | Texto institucional + composición visual con cards flotantes |
| Especialidades | 7 especialidades + tarjeta CTA de orientación |
| Estudios y procedimientos | 6 servicios + escena 3D de cardiología |
| Laboratorio | Franja oscura con 4 cards de vidrio y CTA |
| Servicios más solicitados | Electrocardiograma · PAP · Atención psicológica infantil |
| Etapas de la vida | Niños · Adultos · Adultos mayores · Familias |
| Motivos de consulta | 9 accesos directos a WhatsApp con mensaje contextual |
| Atención psicológica | Sección dedicada con tags y CTA |
| Atención a domicilio | Ilustración 3D de casa + aclaración de disponibilidad por zona |
| Equipo profesional | Bloque institucional **sin datos inventados** + plantilla comentada para cargar profesionales reales |
| Ubicación | Datos de contacto, mapa de Google diferido, botón «Cómo llegar» y nota de cobertura |
| CTA final | Franja azul profunda con el número de WhatsApp |
| Footer | Datos, enlaces y aviso de derechos |
| Flotantes | Botón de WhatsApp en desktop y barra fija inferior en mobile |

## WhatsApp

Todos los CTA abren `https://wa.me/595982420735` con un mensaje precargado
distinto según la sección (especialidad, laboratorio, domicilio, etc.).
Para cambiar el número, reemplazá `595982420735` en todo el archivo.

## Fotografías

La página se entrega **sin fotos de stock**: donde corresponde una imagen real
hay una composición de marca (degradado + emblema) que funciona como
placeholder definitivo hasta cargar el material propio de la policlínica.

Puntos de reemplazo, marcados con comentarios en el HTML:

1. **Sobre Sanitas** — `<div class="ph">` dentro de `.media-frame`
   → `<img src="fotos/policlinica.jpg" alt="..." loading="lazy" decoding="async" width="900" height="760">`
2. **Etapas de la vida** — los cuatro `<div class="ph">` dentro de `.stage .photo`
   → `<img src="fotos/ninos.jpg" alt="..." loading="lazy" decoding="async" width="640" height="480">`

Usar imágenes propias, comprimidas (WebP/AVIF) y con `alt` descriptivo.

## Profesionales

No se cargó ningún nombre, retrato, registro profesional ni años de
experiencia porque esa información no fue provista. En
`index.html` (sección «Profesionales dedicados a tu salud») hay un bloque
comentado listo para completar con: foto, nombre, especialidad, registro
profesional, formación y experiencia. **No completar con datos inventados.**

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

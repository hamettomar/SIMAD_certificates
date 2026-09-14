<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
  <title><?= htmlspecialchars($page_title ?? 'CertificateHub - Verified Workshop Credentials') ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com" rel="preconnect"/>
  <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800&amp;family=Great+Vibes&amp;family=Inter:wght@400;500;600;700&amp;family=Montserrat:wght@400;500;600;700&amp;family=Playfair+Display:ital,wght@0,600;0,700;0,800;1,600&amp;family=Poppins:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400;1,600&amp;display=swap" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
  <style>
    @layer base {
      html, body { margin: 0; padding: 0; }
      body { overscroll-behavior: none; }
      main > :first-child { margin-top: 0 !important; }
      main > :last-child { margin-bottom: 0 !important; }
    }
    .material-symbols-outlined {
      font-family: 'Material Symbols Outlined';
      font-weight: normal;
      font-style: normal;
      font-size: 24px;
      line-height: 1;
      letter-spacing: normal;
      text-transform: none;
      display: inline-block;
      white-space: nowrap;
      word-wrap: normal;
      direction: ltr;
      -webkit-font-feature-settings: 'liga';
      -webkit-font-smoothing: antialiased;
    }
    ::-webkit-scrollbar { display: none; }
  </style>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <script>
    if (typeof QRCode === 'undefined') {
      document.write('<script src="/certificate/public/js/qrcode.min.js"><\/script>');
    }
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script id="tailwind-config">
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          "colors": {
            // SIMAD University Primary: Emerald Green (#21a249)
            "primary": "#21a249",
            "primary-container": "#1b873d",
            "on-primary": "#ffffff",
            "on-primary-container": "#ffffff",
            "primary-fixed": "#e8f6ed",
            "primary-fixed-dim": "#c2ebd0",
            "on-primary-fixed": "#0a3917",
            "on-primary-fixed-variant": "#15632d",

            // SIMAD University Secondary: Sky / Institutional Blue (#5e9fd4)
            "secondary": "#5e9fd4",
            "secondary-container": "#eaf2f9",
            "on-secondary": "#ffffff",
            "on-secondary-container": "#1b4467",
            "secondary-fixed": "#dceaf6",
            "secondary-fixed-dim": "#b7d6ed",
            "on-secondary-fixed": "#0e2c45",
            "on-secondary-fixed-variant": "#2b5f87",

            // Tertiary: Accent Green
            "tertiary": "#21a249",
            "tertiary-container": "#e8f6ed",
            "on-tertiary": "#ffffff",
            "on-tertiary-container": "#0a3917",
            "tertiary-fixed": "#e8f6ed",
            "tertiary-fixed-dim": "#c2ebd0",
            "on-tertiary-fixed": "#0a3917",
            "on-tertiary-fixed-variant": "#15632d",

            // High-Contrast Surfaces & Canvas (#ffffff, #000000, #f8fafc)
            "background": "#f8fafc",
            "on-background": "#000000",
            "surface": "#ffffff",
            "on-surface": "#000000",
            "on-surface-variant": "#475569",
            "surface-bright": "#ffffff",
            "surface-container-lowest": "#ffffff",
            "surface-container-low": "#f8fafc",
            "surface-container": "#f1f5f9",
            "surface-container-high": "#e2e8f0",
            "surface-container-highest": "#cbd5e1",
            "surface-dim": "#e2e8f0",
            "surface-variant": "#f1f5f9",
            "surface-tint": "#21a249",

            // Inverses & Outlines
            "inverse-surface": "#0f172a",
            "inverse-on-surface": "#ffffff",
            "inverse-primary": "#86e3a2",
            "outline": "#94a3b8",
            "outline-variant": "#e2e8f0",

            // Errors
            "error": "#dc2626",
            "error-container": "#fee2e2",
            "on-error": "#ffffff",
            "on-error-container": "#7f1d1d"
          },
          "borderRadius": {
            "DEFAULT": "0.25rem",
            "lg": "0.5rem",
            "xl": "0.75rem",
            "full": "9999px"
          },
          "spacing": {
            "space-xl": "2rem",
            "gutter-mobile": "1rem",
            "space-md": "1rem",
            "max-content-width": "1280px",
            "space-lg": "1.5rem",
            "space-2xs": "0.25rem",
            "space-2xl": "3rem",
            "gutter-desktop": "1.5rem",
            "space-xs": "0.5rem",
            "space-sm": "0.75rem"
          },
          "fontFamily": {
            "label-md": ["Inter", "sans-serif"],
            "headline-md": ["Inter", "sans-serif"],
            "headline-sm": ["Inter", "sans-serif"],
            "caption": ["Inter", "sans-serif"],
            "headline-lg": ["Inter", "sans-serif"],
            "label-sm": ["Inter", "sans-serif"],
            "body-lg": ["Inter", "sans-serif"],
            "body-sm": ["Inter", "sans-serif"],
            "display": ["Inter", "sans-serif"],
            "display-mobile": ["Inter", "sans-serif"],
            "body-md": ["Inter", "sans-serif"]
          },
          "fontSize": {
            "label-md": ["14px", { "lineHeight": "20px", "letterSpacing": "0em", "fontWeight": "500" }],
            "headline-md": ["20px", { "lineHeight": "28px", "letterSpacing": "-0.015em", "fontWeight": "600" }],
            "headline-sm": ["16px", { "lineHeight": "24px", "letterSpacing": "-0.01em", "fontWeight": "600" }],
            "caption": ["11px", { "lineHeight": "14px", "letterSpacing": "0.02em", "fontWeight": "500" }],
            "headline-lg": ["24px", { "lineHeight": "32px", "letterSpacing": "-0.02em", "fontWeight": "600" }],
            "label-sm": ["12px", { "lineHeight": "16px", "letterSpacing": "0.01em", "fontWeight": "500" }],
            "body-lg": ["16px", { "lineHeight": "24px", "letterSpacing": "-0.005em", "fontWeight": "400" }],
            "body-sm": ["12px", { "lineHeight": "16px", "letterSpacing": "0em", "fontWeight": "400" }],
            "display": ["36px", { "lineHeight": "44px", "letterSpacing": "-0.025em", "fontWeight": "600" }],
            "display-mobile": ["28px", { "lineHeight": "36px", "letterSpacing": "-0.02em", "fontWeight": "600" }],
            "body-md": ["14px", { "lineHeight": "20px", "letterSpacing": "0em", "fontWeight": "400" }]
          }
        }
      }
    };
  </script>
</head>

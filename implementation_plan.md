# Implementation Plan: Dynamic User-Preferred Color Themes

Introduce dynamic, user-preferred color themes across the platform based on the user's selected preference (`user_theme` in `user_basic_info` table). The choices are: **Oceanic Blue (Default)**, **Warm Glow**, **Luxe Jewel**, and **Soft Pastel**.

---

## User Review Required

> [!WARNING]
> **Readability Adjustment for Luxe Jewel Theme**
> The design requirements specify that the **Luxe Jewel** normal text should be "deep charcoals" on a "midnight purple" (#2A1B4D) background. Deep charcoal on midnight purple creates a contrast ratio that is extremely low and makes the text virtually unreadable.
>
> **Proposed Solution:**
> For **Luxe Jewel**, we will map the normal text to soft silvers (`#F0E6FF`) or light lavender when displayed directly on the dark background, while reserving dark charcoal for inputs or elements with solid light backgrounds. This maintains visual appeal and guarantees accessibility.

---

## Open Questions

1. **Confetti Dot Pattern Implementation:** For the **Soft Pastel** theme, we plan to implement the "confetti dots" pattern dynamically on the background using a pure CSS `radial-gradient` pattern with alternating coral (#FF6B9D) and mint (#4ECDC4) dots. Please confirm if this approach is preferred over using an external image.

---

## Proposed Changes

We will implement a centralized injection system using PHP's **Output Buffering** (`ob_start`). This intercepts all HTML responses globally (since all pages include `config/config.php`), reads the user's theme choice, and injects the corresponding CSS variables and Tailwind CDN config just before the `</head>` tag. This avoids modifying the 20+ individual page files.

---

### Central Configuration & Helper Layer

#### [MODIFY] [config.php](file:///c:/xampp/htdocs/eventukio/config/config.php)
- Register an output buffer callback at the end of the file.
- The callback will fetch the active user's theme if logged in, generate the dynamic CSS and Tailwind config variables, and inject them before the `</head>` tag.

#### [MODIFY] [functions.php](file:///c:/xampp/htdocs/eventukio/config/functions.php)
- Add a helper function `getThemeStylesAndScripts($theme)` to generate the custom style block and Tailwind config overrides for each of the four themes.

---

### Theme Colors Definition Matrix

| Theme | Base/Background | Glass Panels | Accents | Normal Text | Headings, Logo & Neutrals |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Oceanic Blue** | Warm Off-White (`#FAF7F2`) | `rgba(255, 248, 240, 0.75)` | Electric Blue (`#00BFFF`) | Black (`#1F1F1F`) | Dark Blue (`#2C2C7A`) |
| **Warm Glow** | Sunset Orange (`#FF9A8B`) | `rgba(255, 248, 240, 0.15)` | Terracotta/Coral (`#FF6B6B`) | Deep Charcoal (`#2D2D2D`) | Warm Off-White (`#FAF7F2`) |
| **Luxe Jewel** | Midnight Purple (`#2A1B4D`) | Dark Translucent (`rgba(30, 30, 50, 0.08)`) | Gold (`#E8B923`) & Purple (`#7B4DFF`) | Soft Silver/Lavender (`#F0E6FF`) | Soft Silver (`#F0E6FF`) |
| **Soft Pastel** | Peach-Lavender Gradient (`#FCE7F3` to `#E6E6FA`) | Light Translucent (`rgba(255, 255, 255, 0.25)`) | Coral (`#FF6B9D`) / Mint (`#4ECDC4`) | Deep Gray (`#2D2D2D`) | Deep Gray (`#2D2D2D`) |

---

## Verification Plan

### Automated Tests
- Since this is a visual theme implementation, we will verify the code syntax and look for any PHP errors:
  - Check syntax: `php -l config/config.php` and `php -l config/functions.php`.

### Manual Verification
1. Log in to the system.
2. Go to the Account Settings page (`account.php`).
3. Select each of the four themes one-by-one:
   - **Oceanic Blue**
   - **Warm Glow**
   - **Luxe Jewel**
   - **Soft Pastel**
4. Verify that the background, glass panels, logo, headings, normal texts, and accent elements (buttons, active tabs) dynamically transform across the entire dashboard to match the selected theme.
5. Verify that readability is high in all themes, especially Luxe Jewel (contrast of text against deep purple background).

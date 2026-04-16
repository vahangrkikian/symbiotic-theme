=== Sqft Pricing — Dynamic Print Product Calculator ===
Contributors: sketchsigns
Tags: woocommerce, pricing, print, calculator, configurator
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.1
WC requires at least: 8.0
WC tested up to: 9.0
Stable tag: 2.0.0
License: GPL-2.0-or-later

Axiomprint-style product configurator for WooCommerce print shops. Formula-based pricing with configurable options, quantity tiers, turnaround multipliers, and dynamic option dependencies.

== Description ==

Sqft Pricing transforms any WooCommerce product into a fully configurable print product with dynamic formula-based pricing. Inspired by axiomprint.com's calculator system.

**Key features:**

* **Visual Option Builder** — Create unlimited product options (Shape, Size, Paper Stock, Quantity, Turnaround, etc.) with a drag-and-drop admin interface
* **8 Display Types** — Dropdown, Radio, Card, Pill, Material Card, Size Selector, Quantity Tiers, Turnaround Buttons
* **Formula Engine** — Custom pricing formulas with variable substitution, math functions (floor, round, sqrt), and sheet imposition calculations
* **Dependency Filters** — Options cascade: changing Paper Stock auto-filters available Finishing options
* **Quantity Tiers** — Per-unit pricing that decreases with volume
* **Turnaround Multipliers** — Rush/express pricing with estimated delivery dates
* **Server-Side Security** — All prices recalculated in PHP at cart time; client JS is display-only
* **HPOS Compatible** — Full WooCommerce High-Performance Order Storage support
* **Order Meta** — All selected options saved to order for fulfillment

== Installation ==

1. Upload the `sqft-pricing` folder to `/wp-content/plugins/`
2. Activate through WordPress Plugins menu
3. Edit any product → find the "Print Product Calculator" meta box
4. Check "Enable Print Calculator"
5. Go to **Options** tab → add your option groups and choices
6. Go to **Formula & Pricing** tab → enter your pricing formula
7. Use **Preview** tab to test calculations

== Setup Example: Business Cards ==

= Step 1: Create Option Groups =

| Option | Type | Choices |
|--------|------|---------|
| Shape | Card | Rectangle, Square, Circle |
| Size | Size Selector | 3.5x2, 2x2, Custom Size |
| Paper Stock | Material Card | 14PT Coated, 16PT Coated, 100# Uncoated, etc. |
| Printed Sides | Radio | Front and Back, Front Only |
| Finishing | Dropdown | Glossy, Matte, UV High-Gloss, Uncoated |
| Round Corners | Card | No, 1/8" Round, 1/4" Round |
| Quantity | Quantity Tiers | 50, 100, 250, 500, 1000, 2500, 5000 |
| Turnaround | Turnaround | 3 Business Days, Next Day, Express |

= Step 2: Set Value and Base Cost for Each Choice =

- **Value**: per-unit cost component (multiplied in formula)
- **Base Cost**: flat fee component (added once)
- For Turnaround: Value = multiplier (1.0, 1.2, 1.4), Base = flat fee
- For Quantity: Base = per-unit rate at that tier

= Step 3: Add Dependency Filters =

Example: "Glossy, 2 Sides" finishing only appears when Paper Stock = "14PT Coated Both Sides"

= Step 4: Enter Pricing Formula =

```
(Quantity / floor((12*18) / ((Size$w + 0.25) * (Size$h + 0.25)))
  * (Paper_Stock + Printed_Sides + Finishing + Quantity$base)
  + Shape$base + Size$base + Finishing$base + Printed_Sides$base
  + (Round_Corners * Quantity / 1000 + Round_Corners$base)
) * Turnaround + Turnaround$base
```

== Formula Reference ==

Each variable contributes two values to the formula:
- `VariableSlug` — the item's Value field
- `VariableSlug$base` — the item's Base Cost field

Special variables:
- `Size$w`, `Size$h` — width and height from selected size
- `Quantity` — the selected quantity number
- `Turnaround` — the turnaround multiplier

Math functions: `floor()`, `round()`, `sqrt()`, `ceil()`, `abs()`

== Changelog ==

= 2.0.0 =
* Complete rewrite — Axiomprint-style product configurator
* Visual option builder with 8 display types
* Formula engine with safe math evaluator (no eval)
* Dependency filter system between options
* Quantity tiers with per-unit pricing
* Turnaround multiplier buttons with inline prices
* Server-side price validation at cart time
* HPOS compatibility declaration
* Custom database tables for option storage
* Order meta saves all selected options

= 1.0.0 =
* Initial release — simple sqft pricing with material/print rates

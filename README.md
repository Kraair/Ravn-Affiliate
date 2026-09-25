# Ravn Affiliate
> [!WARNING]
> **Nog in ontwikkeling.** Deze plugin is niet af en niet getest voor productiegebruik. Gebruik op een live site is op eigen risico; instellingen, database-structuur en functienamen kunnen nog wijzigen zonder migratiepad.

Ravn Affiliate is een WordPress-plugin voor het bouwen en beheren van affiliate-productpagina's. De plugin haalt productgegevens en aanbiedingen op bij meerdere affiliate-netwerken, toont ze via blokken en shortcodes, en houdt bij hoe bezoekers erop klikken.

## Ondersteunde platforms

- Bol.com
- Amazon
- Awin
- Daisycon
- TradeTracker

## Functionaliteit 

- **Producten & aanbiedingen** — producten opzoeken op EAN, aanbiedingen van meerdere verkopers aan één product koppelen, en die vergelijken in de front-end.
- **Weergave** — productboxen, lijsten en carrousels via Gutenberg-blokken of shortcodes, met een instelbare stijl (kleuren, randen, kolommen, sterren, popup).
- **Import** — producten/aanbiedingen bulk importeren via bestand of feed-URL, inclusief specifieke import voor Daisycon en TradeTracker.
- **Cron** — automatisch periodiek prijzen en voorraad verversen bij de gekoppelde netwerken.
- **Statistieken** — klikken per product/aanbieder en voorraadwijzigingen bijhouden, zichtbaar in een dashboard-widget en een statistiekenpagina.
- **Extra modules** — FAQ-accordion per product, automatische inhoudsopgave (TOC), schema.org-structured data, en link-cloaking voor affiliate-links.

## Structuur

```
ravn-affiliate.php        Hoofdbestand: headers, constants, autoloader, bootstrap
includes/                 Kernklassen (opties, database, API-koppelingen, shortcodes, blokken, cron)
admin/                     Admin-UI: menu, instellingenpagina's, assets
modules/                   Losstaande functionaliteit: cloaking, faq, import, schema, stats, styling, toc
blocks/                    Gutenberg-blokken (product-list, product-picker, faq, toc)
public/                    Front-end CSS/JS
```

## Vereisten

- WordPress 5.0+
- PHP 7.4+

## Licentie

GPL-2.0-or-later — zie [LICENSE](LICENSE).

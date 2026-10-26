# Sunshine UTM Builder

A free UTM builder that lives inside WordPress. Build tagged links one at a time or hundreds at once, see which Google Analytics 4 channel each one will land in, and share them as short links on your own domain or as QR codes.

It's completely free, with no paid version and no account to sign up for. I build and support it as part of [WP Sunshine](https://wpsunshine.com/).

## What it does

**Build UTM links as you type.** Fill in the page and the tags under Tools → UTM Builder, and your link updates on every keystroke. It handles all five tags (source, medium, campaign, term and content) plus any other parameters you need, like `ref=partner` or `utm_id`. UTM tags already on a link are swapped out, and the link's other parameters are kept.

**Avoid "Unassigned" in Google Analytics 4.** GA4 sorts visits into channels like Organic Social, Email and Paid Search using fixed rules, and a medium it doesn't recognize sends those visits to "Unassigned". The builder shows the channel for every link as you type, and when a link would land in Unassigned it suggests a medium GA4 does know, like `email` instead of `newsletter`.

**Keep tags consistent.** Google Analytics treats `Facebook`, `facebook` and `fb` as three different sources. Formatting rules make tags lowercase, change spaces to underscores or dashes, and remove symbols as you type. Team lists in Settings hold the sources and mediums your team uses, and you can turn on "Only allow these" so nothing else gets through.

**Short links on your own domain.** Turn a long tagged link into `yourdomain.com/x8d89c`, or choose your own ending like `yourdomain.com/spring-sale`. Each short link counts its clicks (bots and link previews aren't counted, and nothing about the visitor is stored) and has its own page with clicks per day for the last 90 days. You can change where a short link goes later, so a link already printed on a flyer keeps working. The Short Links tab also totals clicks by campaign. Short links add no database work to your normal page views. Bitly and Rebrandly are supported too, with your own account.

**QR codes.** Download a QR code for any link, as a PNG or as an SVG that stays sharp in print. Make it from a short link and every scan counts as a click.

**Hundreds of links at once.** The Bulk Builder makes up to 2,000 links in a batch from a list of pages, one link per social network, every combination of a few tag values, or a CSV file. A preview shows the links and their GA4 channel before anything is saved, and every batch is kept with its notes so you can find those links again later or download them as a CSV.

**Presets.** Save the tags you use a lot and fill them in with one click. Presets can hold tokens like `{date}`, `{slug}` and `{page_title}` that are filled in for each link.

**Right where you work.** A "UTM link" button in the admin bar, under each post in your post lists, and in a panel in the block editor sidebar opens the builder with that page already filled in.

**For AI assistants and scripts.** The plugin adds abilities through the WordPress Abilities API (WordPress 6.9 and later), so AI assistants like Claude can build links on your site through the [MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin, using your presets and formatting rules. There's also a REST API endpoint at `/wp-json/sunshine-utm/v1/links`, and both need an administrator login.

**Works with Conversion Bridge.** This plugin builds the links and counts clicks on your short links. If you'd like to see which campaigns turn into sales and form entries, [Conversion Bridge](https://conversionbridgewp.com/) records the UTM tags behind each conversion, and with both plugins active the Short Links tab shows conversions next to clicks.

## Installing

Sunshine UTM Builder is on its way to the WordPress.org plugin directory. Until it's listed there, you can install it from this repository:

1. Download the latest code as a zip (the green **Code** button, then **Download ZIP**).
2. In WordPress, go to Plugins → Add New → Upload Plugin and upload the zip.
3. Activate it, then go to Tools → UTM Builder and build your first link.

It needs WordPress 6.0 or newer and PHP 7.4 or newer. Short links on your own domain need pretty permalinks, which almost every site already has (any setting other than Plain under Settings → Permalinks).

## Privacy

Everything is stored in your own WordPress database, including presets, batches, short links and their click counts. QR codes and the GA4 channel check run in your browser. Links only leave your site when you shorten them with Bitly or Rebrandly, or when the optional link checks load your pages to make sure they work. The plugin doesn't track your visitors and doesn't phone home.

## Documentation

Every feature has a step-by-step guide in the [UTM Builder documentation](https://wpsunshine.com/doccat/utm-builder/), and each screen in the plugin links to the guide for it.

## Questions and bugs

If something isn't working or you have an idea for the plugin, please [open an issue](https://github.com/derekashauer/sunshine-utm-builder/issues) and I'll take a look.

## For developers

The plugin has no build step, so this folder is the plugin as it ships. `readme.txt` is the WordPress.org readme.

The link is worked out in two places that have to agree: `includes/class-url-builder.php` builds links in PHP (the Bulk Builder, the REST API and the AI abilities) and `assets/js/builder.js` builds them in the browser as you type. If you change how links are built in one, change the other the same way.

Hooks you can use:

- `sunshine_utm_shorten` adds another link shortener. Return the short link (or a `WP_Error`) for the service you handle, and `null` for anything else.
- `sunshine_utm_link_params` changes the parameters added to a link built in PHP.
- `sunshine_utm_social_networks` changes the networks offered in the Bulk Builder's social networks option.

## Credits

QR codes are made with [QRCode for JavaScript](https://github.com/kazuhikoarase/qrcode-generator) by Kazuhiko Arase (MIT license). "QR Code" is a registered trademark of DENSO WAVE INCORPORATED.

## License

Sunshine UTM Builder is licensed under GPLv2 or later, as noted in the header of `sunshine-utm-builder.php`.

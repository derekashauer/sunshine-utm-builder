=== Sunshine UTM Builder ===
Contributors: wpsunshine, sccr410
Tags: utm, utm builder, utm generator, campaign url builder, google analytics
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build UTM links one at a time or hundreds at once, with GA4 channel checks, QR codes, and click-counting short links on your own domain.

== Description ==

Sunshine UTM Builder makes the UTM-tagged links you put in emails, ads and social posts, so Google Analytics can tell you where your visitors came from. It also keeps your tags spelled the same way every time, since one stray capital letter or space is enough to split a campaign into two rows in your reports.

It lives under Tools → UTM Builder. Paste a page, fill in the tags, and your link updates as you type. Everything is free, with no paid version and no account to sign up for.

Every feature has a step-by-step guide in the [UTM Builder documentation](https://wpsunshine.com/doccat/utm-builder/?utm_source=wordpress.org&utm_medium=link&utm_campaign=utm-builder-readme), and each screen in the plugin links to the guide for it.

= Build a link as you type =

* All five tags: source, medium, campaign, term and content
* The link updates on every keystroke, with copy and open buttons
* Pick a shortener for the link: your own domain, Bitly or Rebrandly
* Add other parameters too, like `ref=partner` or `utm_id`
* UTM tags already on a link are replaced, and the link's other parameters are kept
* Tags go before any `#anchor` in the link, where Google Analytics can read them
* Suggestions for common sources and mediums, spelled the way Google Analytics expects
* See which Google Analytics 4 channel the link will land in, before you share it
* A QR code for any link, to download as PNG or SVG for flyers, menus and signs

= No more "Unassigned" in Google Analytics 4 =

Google Analytics 4 sorts visits into channels like Organic Social, Email and Paid Search using fixed rules. A medium it doesn't know, like `fb`, `newsletter` or `qr`, sends those visits to "Unassigned", where they're hard to make sense of. The builder shows the channel for every link as you type, and warns you when it would be Unassigned, with a medium to use instead. The Bulk Builder's preview shows it for every link too.

= Consistent tags, every time =

Google Analytics treats "Email", "email" and "e-mail" as three different mediums. The formatting rules clean up tags as you type them, so the same thing is always spelled the same way:

* Make every tag lowercase
* Change spaces to underscores or dashes
* Remove symbols like ! ? and &

Each rule can be turned off in Settings, and the Settings screen shows what a tag will look like before you save.

When one person types facebook, another fb and another Facebook, your reports split three ways. Team lists fix that. List the sources and mediums your team uses in Settings, and the builder offers those as you type. Turn on "Only allow these" and links with anything else aren't made, in the builder, the Bulk Builder, the REST API and the AI tools.

= Presets =

Save the tags you use a lot, like your newsletter or a partner's links, and pick the preset to fill them in. Presets can hold tokens that are filled in for each link:

* `{date}` and `{date_short}` for today's date
* `{slug}` for the last part of the link's path
* `{page_title}` for the title of the post or page, for links on your own site
* `{index}` for the link's number in a batch

= Bulk Builder =

Make many links at once, four ways:

* **A list of links.** Paste them in and they all get the same tags.
* **Social networks.** One link per network, with the network as the source and social as the medium. Facebook, Instagram, X, LinkedIn, Threads, Bluesky, Pinterest, YouTube, TikTok and Reddit are built in.
* **Every combination.** List a few values for any tag and get a link for every mix, which is handy for testing ad versions.
* **A CSV file.** One row per link with its own tags. Blank cells use the shared tags, and any extra column (like `ref`) is added to that row's link as its own parameter. Commas, semicolons and tabs all work, so files saved from Excel in any language are fine.

A preview shows how many links you'll get and the first 20 of them before anything is saved. Lines that aren't web addresses are listed so you can fix them.

= Saved batches =

Every bulk batch is saved, so you can find those links again later.

* Search batches by name or notes
* Notes for the batch, and a note on each link (where it's used, who asked for it)
* Copy one link or every link at once
* Download the batch as a CSV file that opens cleanly in Excel and Google Sheets

= Link checks =

Turn on link checks and each page is loaded before a batch is saved. Links to pages that don't load, like a typo or a deleted post, are marked in the batch so you can fix them before they go out.

= Short links on your own domain =

Long tagged links look messy in social posts and text messages. Short links turn `yourdomain.com/sale/?utm_source=facebook&utm_medium=social&utm_campaign=spring_sale` into `yourdomain.com/x8d89c`, which sends visitors on to the full link so the tags still reach Google Analytics. There's no account to sign up for, and the same long link always gets the same short link. Each one counts its clicks, so you can see which posts people actually clicked.

Short links don't slow down the rest of your site. A short link is only looked up when no page exists at that address, so normal page views never touch it, and a real page always wins over a short link. If you'd like them answered even faster, add a short word in front, like `yourdomain.com/g/x8d89c`, and they're handled before WordPress looks for a page. Links you've already shared keep working if you change that word later.

Each short link has its own page under Tools → UTM Builder → Short Links:

* Clicks per day for the last 90 days, plus totals and the last click
* Change where it goes when a page moves or a promotion ends, and links already printed keep working
* Choose your own ending, like `yourdomain.com/spring-sale`, so people can read and type it
* A QR code, and a delete button

The Short Links tab also totals clicks by campaign, so you can see how a whole campaign did at a glance.

You can also connect your own Bitly or Rebrandly account. Set up as many as you like, and pick one each time under "Shorten with", right above your link.

= Shortcuts =

* A "UTM link" button in the admin bar opens the builder with the page you're looking at already filled in
* A "UTM link" action under each published post and page does the same from your post lists
* A "UTM link" panel in the block editor's sidebar, with a button to build a link to the post and the post's short links with their clicks
* Choose which post types get them

= Let AI build your links =

Ask an AI assistant for "a tagged link to the spring sale page for our Facebook post" and it can make the link on your site, with your presets, your formatting rules and your short links, so its links match yours. The plugin adds three abilities through the WordPress Abilities API (WordPress 6.9 and later):

* **Build a UTM link** for one page, with an optional short link from any shortener you've set up
* **Build many UTM links** with any of the Bulk Builder's four ways, and save them as a batch
* **List UTM presets**, so the AI uses the tag spellings you already use

AI tools like Claude connect to them through the [MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin. Scripts and other tools can run them at `/wp-json/wp-abilities/v1/abilities/sunshine-utm/build-link/run`. Both need an administrator login, and an [Application Password](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/) works well for that.

= REST API =

Send a POST to `/wp-json/sunshine-utm/v1/links` with a list of `urls` and the tags (`source`, `medium`, `campaign`, `term`, `content`) to get tagged links back. Add `"save": true` to keep them as a batch. The endpoint only answers requests from administrators.

= See which links lead to sales =

This plugin makes the links and counts clicks on your short links, and Google Analytics shows the visits they bring. If you'd like to see which campaigns turn into sales and form entries on your WordPress site, [Conversion Bridge](https://conversionbridgewp.com/?utm_source=wordpress.org&utm_medium=link&utm_campaign=utm-builder-readme) records the UTM tags behind each conversion. When both plugins are active, the Short Links tab shows conversions next to clicks for each short link, and Conversion Bridge's own small UTM builder steps aside for this one.

= Credits =

QR codes are made with [QRCode for JavaScript](https://github.com/kazuhikoarase/qrcode-generator) by Kazuhiko Arase (MIT license). "QR Code" is a registered trademark of DENSO WAVE INCORPORATED.

== Installation ==

1. In your WordPress admin, go to Plugins → Add New and search for "Sunshine UTM Builder".
2. Install and activate it.
3. Go to Tools → UTM Builder and build your first link.

== Frequently Asked Questions ==

= Is it really free? =

Yes, every feature is in this plugin and there is no paid version.

= Does it work with Google Analytics 4? =

Yes, GA4 reads the same utm_source, utm_medium, utm_campaign, utm_term and utm_content tags, and you can add utm_id and GA4's other tags under "More parameters".

= Why is a source required? =

Google Analytics needs a source to report a link's campaign, so the builder won't make a link without one.

= Does it track clicks on my links? =

Short links on your own site count their clicks. You'll find each link's total, its clicks per day and its last click under Tools → UTM Builder → Short Links. Only the number is stored, nothing about the person who clicked. Bots and link previews (like the one Facebook makes when you post a link) aren't counted. You can delete a short link there too, and it stops working right away.

Full-length links and Bitly or Rebrandly links aren't counted by the plugin. The visits they bring show up in Google Analytics, or whichever analytics tool you use that reads UTM tags.

= Is the Google Analytics 4 channel check always right? =

It follows Google's published default channel rules. GA4 also sorts by source using a long list of sites, and only well-known ones (Google, Facebook, YouTube and the like) are included here, so for an unusual source the check may warn when GA4 wouldn't. It won't tell you a link is fine when GA4 would call it Unassigned. If you've set up your own channel groups in GA4, those aren't known to the plugin.

= Can I change a short link after I've shared it? =

Yes, where it goes. Open the link under Tools → UTM Builder → Short Links and change "Goes to", and everyone who clicks the link from then on lands on the new page, even from something already printed. Changing the ending (the part after your domain) is different: the old ending stops working, so choose the ending before you share the link.

= Do QR codes stop working? =

No. A QR code just holds a link, and it's made in your browser with nothing stored anywhere else. Make QR codes from a short link on your own domain and you can change where it goes later without reprinting, and the scans are counted as clicks.

= What happens to parameters already on my link? =

Any UTM tags already on the link are replaced with the new ones. Other parameters, like `?ref=abc`, stay as they are.

= What should my CSV look like? =

The first row names the columns. You need a column called `url`, and you can add any of `source`, `medium`, `campaign`, `term` and `content` (or `utm_source` and so on), like this:

`url,source,medium,campaign`
`https://example.com/sale/,newsletter,email,spring_sale`

= How many links can a batch have? =

A batch can have up to 2,000 links, and short links on your own domain work for all of them. Bitly, Rebrandly and link checks make one request per link, so they work on batches of up to 50.

= Can an AI assistant make links for me? =

Yes, through the Abilities API and the MCP Adapter plugin (see "Let AI build your links" above). The AI uses the same formatting rules and presets as the link builder, and needs an administrator login to make links.

= Do short links on my own domain need anything set up? =

They're on from the start and only need pretty permalinks, which almost every site already has (any setting other than Plain under Settings → Permalinks).

= Where is my data stored? =

Everything is stored in your WordPress database, including short links on your own domain and their click counts, and presets and batches never leave your site. QR codes and the Google Analytics 4 channel check run in your browser. Links are only sent out when you shorten them with Bitly or Rebrandly, or when link checks load your pages. When Conversion Bridge is active, its conversions are read from the same database.

= What happens to my data if I delete the plugin? =

It's kept, so reinstalling brings back your presets, batches and short links. Turn on "Delete presets, batches, short links and settings when the plugin is deleted" in Settings if you'd like it all removed.

== External services ==

This plugin only contacts other services for the features below, and only when you use them.

= Bitly =

If you choose Bitly as your link shortener in Settings, the link being shortened is sent to Bitly's API (api-ssl.bitly.com) along with your access token. That happens when you pick Bitly under "Shorten with" in the builder, when you create a batch with shortening on, and when the AI tools or the API ask for a short link. Bitly sends back the short link. [Terms of service](https://bitly.com/pages/terms-of-service), [privacy policy](https://bitly.com/pages/privacy).

= Rebrandly =

If you choose Rebrandly as your link shortener in Settings, the link being shortened is sent to Rebrandly's API (api.rebrandly.com) along with your API key, at the same times as for Bitly above. Rebrandly sends back the short link. [Terms and conditions](https://www.rebrandly.com/terms-conditions), [privacy policy](https://www.rebrandly.com/privacy-policy).

= Link checks =

If you turn on link checks in Settings, each page a batch links to is requested once when the batch is created, to see whether it loads. The request goes to whatever site that page is on, and nothing is sent besides the request itself. Link checks are off by default.

== Screenshots ==

1. The link builder, with the Google Analytics 4 channel check and a QR code
2. The Bulk Builder with a preview of the links it will make
3. A saved batch with copy buttons, notes and the CSV download
4. Short links with clicks by campaign
5. One short link's page, with clicks per day and the forms to change it
6. Presets with tokens
7. Settings for formatting, team lists, link checks and short links

== Changelog ==

= 1.0.0 =
* First release

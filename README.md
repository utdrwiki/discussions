<p align="center"><a href="https://github.com/utdrwiki/discussions/releases/latest" alt="Latest Release">
<img src="https://img.shields.io/github/v/release/utdrwiki/discussions"/></a></p>

<div align="center"><h1>🗯️ Discourse</h1></div>

**Discourse** is an in-development MediaWiki extension that acts as a compatibility layer between the forum software Discourse and the wiki hosting software MediaWiki. It is unstable for production use outside of the Undertale Wiki and Deltarune Wiki environment.

## Features
- Allows every wiki user to have a Discourse account, it uses wiki accounts in-place of separate Discourse accounts.
- It adds an area for Discourse profile elements to the top of every page related to user actions, this includes user pages (`User:Example`), user talk pages (`User_talk:Example`) and user contribution pages (`Special:Contributions/Example`).
- It adds a users Discourse avatar, their wiki edit count and the amount of Discourse posts they've made, along with related user page links at the bottom of the Discourse profile element, these links go to each persons `Special:Contributions` entry, `User_talk` page and to their Discourse posts page.
- It replaces the links to article talk pages with redirect links to Discourse tag categories for a respective pages title.
- It shows a list of relevant post previews at the bottom of the current page, and it provides a maintenance script that syncs Discourse tags with qualifying (mainspace, no sub-pages) articles on the wiki.
- It has the option to combine user groups from Discourse and MediaWiki together, eliminating the need to manually promote users through Discourse and MediaWiki.

## Requirements
* [MediaWiki](https://www.mediawiki.org) 1.43.0 or later
* [Discourse](https://github.com/discourse/discourse) Latest version

## Installation
1. Install [Discourse](https://github.com/discourse/discourse) before you proceed with setting up the extension.
2. Follow these instructions to setup your Discourse installation: https://github.com/discourse/discourse/blob/main/docs/INSTALL.md
3. [Download](https://github.com/utdrwiki/discussions/archive/master.zip) and place the file(s) in a directory called `Discourse` in your `extensions/` folder.
4. Add the following code at the bottom of your LocalSettings.php and **after all other extensions**:
```php
wfLoadExtension( "Discourse" );
```
**Done** ✔️ - Navigate to Special:Version on your wiki to verify that the extension is successfully installed.

## Configuration options
> ❗ **The Discourse extension requires configuration before you can begin using it.**

Name | Description | Values | Default | Required?
:--- | :--- | :--- | :--- | :---
`$wgDiscourseApiKey` | The API key used to communicate with the Discourse installation. | `string` | `null` | ✔️ Yes
`$wgDiscourseApiUsername` | The username used to process all API requests for the Discourse extension. | `string` | `null` | ✔️ Yes
`$wgDiscourseBaseUrl` | Base URL used to define the URL location of your Discourse installation. | `string` | `null` | ✔️ Yes
`$wgDiscourseBaseUrlInternal` | The URL that MediaWiki will use to request data from Discourse. If Discourse is running on the same host as MediaWiki, you can use this option to avoid unnecessary round trips. | `string` | `null` | ❗ No, but recommended if sharing the same host.
`$wgDiscourseConnectSecret` | The value you set under Discourse's `discourse_connect_secret` setting. | `string` | `null` | ✔️ Yes
`$wgDiscourseUnixSocket` | The path to the Unix socket that Discourse is listening on. If Discourse is running on the same host as MediaWiki and using Unix sockets, you can use this option to avoid unnecessary round trips. | `command` | `null` | ❗ No, but recommended if sharing the same host.
`$wgDiscourseDefaultAvatarColor` | The default color of the avatar background when there is no avatar image available | `integer` | `#FF0000` | ❌ No
`$wgDiscourseEnableProfile` | Whether to enable the Discourse profile integration. | `integer` | `true` - enable; `false` - disable | ❌ No
`$wgDiscourseGroupMap` | A mapping of MediaWiki groups to Discourse groups and permissions. | `integer` | `true` - enable; `false` - disable | ❌ No
`$wgDiscourseSuppressWelcomeMessage` | Whether to suppress the Discourse welcome message | `integer` | `true` - enable; `false` - disable | ❌ No

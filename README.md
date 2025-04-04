<p align="center"><a href="https://github.com/utdrwiki/discussions/releases/latest" alt="Latest Release">
<img src="https://img.shields.io/github/v/release/utdrwiki/discussions"/></a></p>

<div align="center"><h1>📰 Discourse</h1></div>

**Discourse** is an in-development MediaWiki extension that acts as a compatibility layer between Discourse and MediaWiki. It is unstable for production use outside of the Undertale Wiki and Deltarune Wiki environment.

## Features
- Adds a social Discourse profile element to the top of every user page with avatars and edit count and posts count.
- Also links to every persons user page with links to `Special:Contributions`, `User_talk` page and a page of their Discourse posts.
- Allows every wiki user to have a Discourse account without the need to create one through Discourse.
- Has the option to combine user groups from Discourse and MediaWiki together, eliminating the need to manually promote staff members through Discourse and MediaWiki.

## Installation
1. Install [Discourse](https://github.com/discourse/discourse) before you proceed with setting up the extension.
2. Follow these instructions to setup your Discourse installation: https://github.com/discourse/discourse/blob/main/docs/INSTALL.md
3. [Download](https://github.com/utdrwiki/discussions/archive/master.zip) and place the file(s) in a directory called `Discourse` in your `extensions/` folder.
4. Add the following code at the bottom of your LocalSettings.php and **after all other extensions**:
```php
wfLoadExtension( "Discourse" );
```
**Done** ✔️ - Navigate to Special:Version on your wiki to verify that the extension is successfully installed.

## Requirements
* [MediaWiki](https://www.mediawiki.org) 1.43.0 or later
* [Discourse](https://github.com/discourse/discourse) Latest version

## Configurations
> ❗ **Discourse requires configuration before you can begin using it.**

### Configuration options
Name | Description | Values | Default
:--- | :--- | :--- | :---
`$wgDiscourseApiKey` | The API key used to communicate with the Discourse installation. | `string` | `null`
`$wgDiscourseApiUsername` | The username used to process all API requests for the Discourse extension. | `string` | `null`
`$wgDiscourseBaseUrl` | Base URL used to define the URL location of your Discourse installation. | `string` | `null`
`$wgDiscourseBaseUrlInternal` | The URL that MediaWiki will use to request data from Discourse. If Discourse is running on the same host as MediaWiki, you can use this option to avoid unnecessary round trips. | `string` | `null`
`$wgDiscourseConnectSecret` | The value you set under Discourse's `discourse_connect_secret` setting. | `string` | `null`
`$wgDiscourseUnixSocket` | The path to the Unix socket that Discourse is listening on. If Discourse is running on the same host as MediaWiki and using Unix sockets, you can use this option to avoid unnecessary round trips. | `command` | `null`
`$wgDiscourseDefaultAvatarColor` | The default color of the avatar background when there is no avatar image available | `integer` | `#FF0000`
`$wgDiscourseEnableProfile` | Whether to enable the Discourse profile integration. | `integer` | `true` - enable; `false` - disable
`$wgDiscourseGroupMap` | A mapping of MediaWiki groups to Discourse groups and permissions. | `integer` | `true` - enable; `false` - disable
`$wgDiscourseSuppressWelcomeMessage` | Whether to suppress the Discourse welcome message | `integer` | `true` - enable; `false` - disable

# External Links: Open in New Tab (VSA)
A lightweight, secure, and efficient WordPress plugin developed by Vidyasagar Academy (VSA) that automatically opens all external links on your website in a new browser tab, while keeping internal links untouched.
This plugin includes whitelist domains, export/import of settings, and a clean settings page inside the WordPress admin.

## Features
Automatically adds target="_blank" to all external links
Adds rel="noopener noreferrer" for security
Whitelist domains that should not open in new tabs
Complete Export / Import of plugin settings (JSON)

### Works in:
Posts & Pages
Menus
Widgets
Comments
Attachment links
Dynamically injected links (via JavaScript observer)

## Plugin settings available under Settings → External Links (VSA)
No performance impact — optimized DOM scanning
No tracking, no external API usage, no data storage beyond settings

## Installation
[Download the latest release ZIP](https://github.com/dsvakola/vsa-external-links) from the Releases section
Upload it in your WordPress Dashboard under:
Plugins → Add New → Upload Plugin
Activate the plugin

Go to: Settings → External Links (VSA) to configure whitelist or export/import settings

#### Settings Options
Enabled
Toggle the plugin ON or OFF.
Whitelist Domains
Add domains (one per line) that should not open in a new tab.

**Example:**
vsa.edu.in
youtube.com

### Export Settings
Exports a .json file containing your plugin configuration.

### Import Settings
Upload a previously exported .json file to restore your settings.

## Security
Automatically adds noopener and noreferrer
Prevents window.opener vulnerabilities
Sanitizes all admin input
Uses WordPress nonces for form protection
Does not collect or transmit any website data

## Changelog
v1.10 (Current Release)
Added Export/Import Settings (JSON)
Added Whitelist Domains Management UI
Added Settings link in plugin list
Improved security attributes
Cleaned plugin metadata

## Author
Vidyasagar Academy (VSA), Prof. Dr. Dattaraj Vidyasagar
Website: https://vsa.edu.in/dattaraj-vidyasagar/
Contact: https://vsa.edu.in/contact/

## License
This plugin is released under the MIT License.

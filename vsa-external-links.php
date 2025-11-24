<?php
/**
 * Plugin Name: External Links: Open in New Tab (VSA)
 * Plugin URI:  https://vsa.edu.in/plugins/external-links/
 * Description: Force external links to open in a new tab/window | By VSA | Contact Support for your feedback
 * Version:     1.10
 * Author:      Vidyasagar Academy
 * Author URI:  https://vsa.edu.in/
 * Text Domain: vsa-external-links
 * Update URI:  false
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * ---------------------------------------------------------------------
 * Options: defaults and helper
 * ---------------------------------------------------------------------
 */
function vsa_get_default_options() {
    return array(
        'enabled'   => 1,
        'whitelist' => "vsa.edu.in\nwww.vsa.edu.in",
    );
}

/**
 * Get plugin options (merged with defaults)
 *
 * @return array
 */
function vsa_get_options() {
    $opts = get_option( 'vsa_external_links_options', array() );
    $defaults = vsa_get_default_options();
    return wp_parse_args( $opts, $defaults );
}

/**
 * ---------------------------------------------------------------------
 * Helper: Is the URL external to the current site and not whitelisted?
 *
 * @param string $url
 * @return bool
 */
function vsa_is_external_url( $url ) {
    $opts = vsa_get_options();
    $enabled = isset( $opts['enabled'] ) ? (bool) $opts['enabled'] : true;
    if ( ! $enabled ) {
        return false;
    }

    if ( empty( $url ) || ! is_string( $url ) ) {
        return false;
    }

    // Parse URL
    $url_parsed = wp_parse_url( $url );
    if ( empty( $url_parsed['host'] ) ) {
        // Relative link -> internal
        return false;
    }

    $host = strtolower( $url_parsed['host'] );

    // Get site host(s)
    $site_host = strtolower( wp_parse_url( home_url(), PHP_URL_HOST ) );
    if ( ! $site_host ) {
        return false;
    }

    // If host is same as site host, it's internal
    if ( strcasecmp( $host, $site_host ) === 0 ) {
        return false;
    }

    // Check whitelist
    $whitelist_raw = isset( $opts['whitelist'] ) ? $opts['whitelist'] : '';
    if ( $whitelist_raw ) {
        $normalized = str_replace( array("\r\n","\r"), "\n", $whitelist_raw );
        $lines = array_map( 'trim', explode( "\n", $normalized ) );
        foreach ( $lines as $line ) {
            if ( empty( $line ) ) {
                continue;
            }
            // strip protocol and path if user pasted full URLs
            $clean = preg_replace( '#^https?://#i', '', $line );
            $clean_host = trim( parse_url( 'http://' . $clean, PHP_URL_HOST ) );
            if ( ! $clean_host ) {
                continue;
            }
            $wl = strtolower( $clean_host );
            // exact host match
            if ( $host === $wl ) {
                return false;
            }
            // allow matching subdomains: e.g., whitelist example.com should match foo.example.com
            if ( preg_match( '/(^|\.)' . preg_quote( $wl, '/' ) . '$/', $host ) ) {
                return false;
            }
        }
    }

    return true;
}

/**
 * ---------------------------------------------------------------------
 * Server-side: modify links in HTML content
 * ---------------------------------------------------------------------
 *
 * Use DOMDocument to set target="_blank" and rel attributes for external links.
 * This acts as the fallback for users with JS disabled and covers many WP outputs.
 */
function vsa_add_target_blank_to_content_links( $content ) {
    if ( empty( $content ) ) {
        return $content;
    }

    // If plugin disabled, return content unchanged
    $opts = vsa_get_options();
    if ( isset( $opts['enabled'] ) && ! $opts['enabled'] ) {
        return $content;
    }

    // Use DOMDocument for safer HTML parsing. Suppress warnings on malformed HTML.
    libxml_use_internal_errors( true );
    $dom = new DOMDocument();

    // Ensure proper encoding when loading HTML fragments.
    $loaded = $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
    libxml_clear_errors();
    if ( ! $loaded ) {
        // If parsing failed, return original content.
        return $content;
    }

    $anchors = $dom->getElementsByTagName( 'a' );
    foreach ( $anchors as $anchor ) {
        $href = $anchor->getAttribute( 'href' );
        if ( vsa_is_external_url( $href ) ) {
            // Set target and rel attributes
            $anchor->setAttribute( 'target', '_blank' );

            // Add noopener noreferrer to mitigate window.opener risk
            $existing_rel = $anchor->getAttribute( 'rel' );
            $rels = array_filter( array_map( 'trim', preg_split( '/\s+/', $existing_rel ) ) );
            $rels = array_unique( array_merge( $rels, array( 'noopener', 'noreferrer' ) ) );
            $anchor->setAttribute( 'rel', implode( ' ', $rels ) );
        }
    }

    // Return processed HTML
    $body = $dom->saveHTML();
    // Remove the xml encoding hack we added
    $body = preg_replace( '/^<\\?xml.*?\\?>/', '', $body );
    return $body;
}

/**
 * Apply server side filter to post content and excerpt.
 */
add_filter( 'the_content', 'vsa_add_target_blank_to_content_links', 20 );
add_filter( 'the_excerpt', 'vsa_add_target_blank_to_content_links', 20 );

/**
 * Apply to widget text (legacy) and widgets output.
 */
add_filter( 'widget_text', 'vsa_add_target_blank_to_content_links', 20 );
add_filter( 'widget_text_content', 'vsa_add_target_blank_to_content_links', 20 );

/**
 * Apply to comments text
 */
add_filter( 'comment_text', 'vsa_add_target_blank_to_content_links', 20 );

/**
 * Apply to attachment links (when WP generates attachment links)
 */
add_filter( 'wp_get_attachment_link', function( $markup ) {
    return vsa_add_target_blank_to_content_links( $markup );
}, 20 );

/**
 * Apply to menu item attributes during rendering (so menus also get attributes)
 *
 * @param array  $atts
 * @param object $item
 * @param object $args
 * @param int    $depth
 * @return array
 */
function vsa_nav_menu_set_target_for_external_links( $atts, $item, $args, $depth ) {
    if ( isset( $atts['href'] ) && vsa_is_external_url( $atts['href'] ) ) {
        $atts['target'] = '_blank';
        $existing_rel = isset( $atts['rel'] ) ? $atts['rel'] : '';
        $rels = array_filter( array_map( 'trim', preg_split( '/\s+/', $existing_rel ) ) );
        $rels = array_unique( array_merge( $rels, array( 'noopener', 'noreferrer' ) ) );
        $atts['rel'] = implode( ' ', $rels );
    }
    return $atts;
}
add_filter( 'nav_menu_link_attributes', 'vsa_nav_menu_set_target_for_external_links', 10, 4 );

/**
 * ---------------------------------------------------------------------
 * Frontend JS: ensure any dynamically-inserted links are handled
 * ---------------------------------------------------------------------
 * Register an inline script that adjusts links on DOM ready and observes DOM changes.
 */
function vsa_enqueue_frontend_script() {
    if ( is_admin() ) {
        return;
    }

    $opts = vsa_get_options();
    if ( isset( $opts['enabled'] ) && ! $opts['enabled'] ) {
        return;
    }

    // Register an empty handle and add inline script
    wp_register_script( 'vsa-external-links-script', '' );
    wp_enqueue_script( 'vsa-external-links-script' );

    $site_host = esc_js( wp_parse_url( home_url(), PHP_URL_HOST ) );
    $inline_js = <<<JS
(function() {
    'use strict';
    function isExternalLink(url) {
        try {
            if (!url) return false;
            var a = document.createElement('a');
            a.href = url;
            if (!a.hostname) return false;
            return a.hostname.toLowerCase() !== '{$site_host}';
        } catch (e) {
            return false;
        }
    }

    function fixLinks(context) {
        context = context || document;
        var anchors = context.querySelectorAll('a[href]');
        for (var i = 0; i < anchors.length; i++) {
            var anchor = anchors[i];
            var href = anchor.getAttribute('href');
            if (isExternalLink(href)) {
                if (!anchor.getAttribute('target')) {
                    anchor.setAttribute('target', '_blank');
                }
                var rel = anchor.getAttribute('rel') || '';
                var relParts = rel.split(/\\s+/).filter(Boolean);
                if (relParts.indexOf('noopener') === -1) relParts.push('noopener');
                if (relParts.indexOf('noreferrer') === -1) relParts.push('noreferrer');
                anchor.setAttribute('rel', relParts.join(' '));
            }
        }
    }

    // On DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { fixLinks(); });
    } else {
        fixLinks();
    }

    // Observe for later DOM changes (e.g., dynamic content)
    try {
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                for (var i = 0; i < mutation.addedNodes.length; i++) {
                    var node = mutation.addedNodes[i];
                    if (node.nodeType === 1) { // element
                        fixLinks(node);
                    }
                }
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    } catch (e) {
        // MutationObserver unsupported - ignore
    }
})();
JS;

    wp_add_inline_script( 'vsa-external-links-script', $inline_js );
}
add_action( 'wp_enqueue_scripts', 'vsa_enqueue_frontend_script' );

/**
 * ---------------------------------------------------------------------
 * Admin: Settings page, registration, sanitization, and UI
 * ---------------------------------------------------------------------
 */

/**
 * Register settings and fields
 */
add_action( 'admin_init', 'vsa_external_links_register_settings' );
function vsa_external_links_register_settings() {
    register_setting(
        'vsa_external_links_group',                // option_group
        'vsa_external_links_options',              // option_name (array)
        'vsa_external_links_sanitize_options'      // sanitize_callback
    );

    add_settings_section(
        'vsa_external_links_main',                 // id
        'External Links Settings',                 // title
        'vsa_external_links_main_cb',              // callback
        'vsa-external-links'                       // page (matches add_options_page slug)
    );

    add_settings_field(
        'enabled',
        'Enable behaviour',
        'vsa_external_links_field_enabled_cb',
        'vsa-external-links',
        'vsa_external_links_main'
    );

    add_settings_field(
        'whitelist',
        'Whitelisted domains (one per line)',
        'vsa_external_links_field_whitelist_cb',
        'vsa-external-links',
        'vsa_external_links_main'
    );
}

function vsa_external_links_main_cb() {
    echo '<p>Configure which external links should open in a new tab and which domains should be ignored (whitelisted).</p>';
}

function vsa_external_links_field_enabled_cb() {
    $opts = vsa_get_options();
    $enabled = isset( $opts['enabled'] ) ? (bool) $opts['enabled'] : true;
    echo '<label><input type="checkbox" name="vsa_external_links_options[enabled]" value="1" ' . checked( 1, $enabled, false ) . '> Enabled (force external links to open in new tab)</label>';
}

function vsa_external_links_field_whitelist_cb() {
    $opts = vsa_get_options();
    $whitelist = isset( $opts['whitelist'] ) ? $opts['whitelist'] : vsa_get_default_options()['whitelist'];
    echo '<textarea name="vsa_external_links_options[whitelist]" rows="6" cols="60" style="font-family:monospace;">' . esc_textarea( $whitelist ) . '</textarea>';
    echo '<p class="description">Add domains (no protocol). Example: <code>youtube.com</code> or <code>sub.example.com</code>. Wildcards are not supported; list each domain on its own line.</p>';
}

/**
 * Sanitize options
 */
function vsa_external_links_sanitize_options( $input ) {
    $output = array();

    $output['enabled'] = isset( $input['enabled'] ) && ( $input['enabled'] == 1 || $input['enabled'] === '1' ) ? 1 : 0;

    // Sanitize whitelist: keep only domain-like characters and line breaks
    $white = isset( $input['whitelist'] ) ? $input['whitelist'] : '';
    // Normalize line endings
    $white = str_replace( array("\r\n","\r"), "\n", $white );
    $lines = array_map( 'trim', explode( "\n", $white ) );
    $clean_lines = array();

    foreach ( $lines as $line ) {
        if ( empty( $line ) ) continue;
        // strip protocol and path if user pasted full URLs
        $line = preg_replace( '#^https?://#i', '', $line );
        $host = trim( parse_url( 'http://' . $line, PHP_URL_HOST ) );
        if ( $host ) {
            $clean_lines[] = sanitize_text_field( strtolower( $host ) );
        }
    }

    $output['whitelist'] = implode( "\n", array_unique( $clean_lines ) );

    return $output;
}

/**
 * Add settings page to Settings menu
 */
add_action( 'admin_menu', 'vsa_external_links_add_admin_menu' );
function vsa_external_links_add_admin_menu() {
    add_options_page(
        'External Links (VSA)',         // page title
        'External Links (VSA)',         // menu title
        'manage_options',               // capability
        'vsa-external-links',           // menu slug
        'vsa_external_links_options_page' // callback
    );
}

/**
 * Render settings page
 */
function vsa_external_links_options_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Logo path - put your logo at assets/vsa-logo-small.png
    $logo_path = plugin_dir_path( __FILE__ ) . 'assets/vsa-logo-small.png';
    $logo_url  = plugin_dir_url( __FILE__ ) . 'assets/vsa-logo-small.png';
    ?>
    <div class="wrap">
        <h1>External Links: Open in New Tab (VSA)</h1>
        <div style="display:flex;align-items:center;gap:20px;margin-bottom:20px;">
            <?php if ( file_exists( $logo_path ) ): ?>
                <img src="<?php echo esc_url( $logo_url ); ?>" alt="VSA Logo" style="height:64px;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.1);" />
            <?php endif; ?>
            <div>
                <p style="margin:0;font-size:1.05em;">Force external links to open in a new tab/window for improved UX and safety.</p>
                <p style="margin:4px 0 0 0;color:#666;">Version 1.10 | By Vidyasagar Academy</p>
            </div>
        </div>

        <form method="post" action="options.php">
            <?php
                settings_fields( 'vsa_external_links_group' );
                do_settings_sections( 'vsa-external-links' );
                submit_button();
            ?>
        </form>
    </div>
    <?php
}

/**
 * ---------------------------------------------------------------------
 * Plugin row meta: remove WP default Version/Author lines and add custom meta
 * ---------------------------------------------------------------------
 */

/**
 * Remove WordPress default version/author meta so it does not duplicate our custom line.
 * Runs at priority 5 to act before we add our custom meta.
 */
add_filter( 'plugin_row_meta', 'vsa_remove_default_plugin_meta', 5, 4 );
function vsa_remove_default_plugin_meta( $plugin_meta, $plugin_file, $plugin_data, $status ) {

    $this_plugin = plugin_basename( __FILE__ );
    if ( $plugin_file !== $this_plugin ) {
        return $plugin_meta;
    }

    // Remove common default meta lines that contain "Version" or "By "
    foreach ( $plugin_meta as $key => $value ) {
        if ( stripos( $value, 'Version' ) !== false || stripos( trim( strip_tags( $value ) ), 'By ' ) === 0 ) {
            unset( $plugin_meta[ $key ] );
        }
    }

    return array_values( $plugin_meta );
}

/**
 * Add our single custom meta: "Version 1.10 | By VSA | Contact Support"
 */
add_filter( 'plugin_row_meta', 'vsa_add_plugin_row_meta', 10, 4 );
function vsa_add_plugin_row_meta( $plugin_meta, $plugin_file, $plugin_data, $status ) {
    $this_plugin_basename = plugin_basename( __FILE__ );
    if ( $plugin_file !== $this_plugin_basename ) {
        return $plugin_meta;
    }

    $contact_url = esc_url( 'https://vsa.edu.in/contact/' );

    $custom_meta = '<span style="display:inline-block;margin-right:8px">Version 1.10 | By VSA | <a href="' . $contact_url . '" target="_blank" rel="noopener noreferrer">Contact Support</a></span>';

    array_unshift( $plugin_meta, $custom_meta );

    return $plugin_meta;
}

/**
 * Add quick link "Settings" on Plugins page
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'vsa_external_links_plugin_action_links' );
function vsa_external_links_plugin_action_links( $links ) {
    $settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=vsa-external-links' ) ) . '">Settings</a>';
    array_unshift( $links, $settings_link );
    return $links;
}

/**
 * Show admin notice after activation reminding to check settings if options are not present.
 */
add_action( 'admin_notices', 'vsa_admin_activation_notice' );
function vsa_admin_activation_notice() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Only show if options are missing (first activation)
    if ( false === get_option( 'vsa_external_links_options' ) ) {
        echo '<div class="notice notice-info is-dismissible"><p><strong>External Links (VSA):</strong> Please review settings at <a href="' . esc_url( admin_url( 'options-general.php?page=vsa-external-links' ) ) . '">Settings → External Links (VSA)</a> to configure whitelisted domains.</p></div>';
    }
}

/**
 * ---------------------------------------------------------------------
 * Uninstall cleanup (no persistent data stored beyond one DB option)
 * ---------------------------------------------------------------------
 */
register_uninstall_hook( __FILE__, 'vsa_uninstall_cleanup' );
function vsa_uninstall_cleanup() {
    // Remove plugin option on uninstall
    delete_option( 'vsa_external_links_options' );
}

/**
 * ---------------------------------------------------------------------
 * Prevent WP from showing update notices for this plugin (fallback).
 * ---------------------------------------------------------------------
 */
add_filter( 'site_transient_update_plugins', 'vsa_suppress_plugin_update_notice' );
function vsa_suppress_plugin_update_notice( $transient ) {
    if ( empty( $transient ) || empty( $transient->response ) ) {
        return $transient;
    }

    // Build the plugin key as WP stores it: 'folder/mainfile.php'
    $this_plugin = plugin_basename( __FILE__ );

    if ( isset( $transient->response[ $this_plugin ] ) ) {
        unset( $transient->response[ $this_plugin ] );
    }

    return $transient;
}

/**
 * End of plugin file.
 */

<?php
require_once(WPCF_EMBEDDED_ABSPATH . '/common/visual-editor/editor-addon.class.php');
require_once WPCF_EMBEDDED_ABSPATH . '/includes/post-relationship.php';

if ( defined( 'DOING_AJAX' ) ) {
    require_once WPCF_EMBEDDED_INC_ABSPATH . '/ajax.php';
    add_action( 'wp_ajax_wpcf_ajax', 'wpcf_ajax_embedded' );
}

/**
 * admin_init hook.
 */
function wpcf_embedded_admin_init_hook() {
    // Add callbacks for post edit pages
    add_action( 'load-post.php', 'wpcf_admin_post_page_load_hook' );
    add_action( 'load-post-new.php', 'wpcf_admin_post_page_load_hook' );

    // Add callback for 'media-upload.php'
    add_filter( 'get_media_item_args', 'wpcf_get_media_item_args_filter' );

    // Add save_post callback
    add_action( 'save_post', 'wpcf_admin_save_post_hook', 10, 2 );

    // Render messages
    wpcf_show_admin_messages();

    // Render JS settings
    add_action( 'admin_head', 'wpcf_admin_render_js_settings' );

    // Media insert code
    if ( isset( $_GET['wpcf-fields-media-insert'] )
            || (isset( $_SERVER['HTTP_REFERER'] ) && strpos( $_SERVER['HTTP_REFERER'],
                    'wpcf-fields-media-insert=1' )) ) {
        require_once WPCF_EMBEDDED_INC_ABSPATH . '/fields/file.php';
        // Add types button
        add_filter( 'attachment_fields_to_edit',
                'wpcf_fields_file_attachment_fields_to_edit_filter', 10, 2 );
        // Add JS
        add_action( 'admin_head', 'wpcf_fields_file_media_admin_head' );
        // Filter media TABs
        add_filter( 'media_upload_tabs',
                'wpcf_fields_file_media_upload_tabs_filter' );
    }

    register_post_type( 'wp-types-group',
            array(
        'public' => false,
        'label' => 'Types Groups',
        'can_export' => false,
            )
    );

    add_filter( 'icl_custom_fields_to_be_copied',
            'wpcf_custom_fields_to_be_copied', 10, 2 );

    // WPML editor filters
    add_filter( 'icl_editor_cf_name', 'wpcf_icl_editor_cf_name_filter' );
    add_filter( 'icl_editor_cf_description',
            'wpcf_icl_editor_cf_description_filter', 10, 2 );
    add_filter( 'icl_editor_cf_style', 'wpcf_icl_editor_cf_style_filter', 10, 2 );
    // Initialize translations
    if ( function_exists( 'icl_register_string' )
            && defined( 'WPML_ST_VERSION' )
            && !get_option( 'wpcf_strings_translation_initialized', false ) ) {
        wpcf_admin_bulk_string_translation();
        update_option( 'wpcf_strings_translation_initialized', 1 );
    }
}

/**
 * save_post hook.
 * 
 * @param type $post_ID
 * @param type $post 
 */
function wpcf_admin_save_post_hook( $post_ID, $post ) {
    require_once WPCF_EMBEDDED_INC_ABSPATH . '/fields.php';
    require_once WPCF_EMBEDDED_INC_ABSPATH . '/fields-post.php';
    wpcf_admin_post_save_post_hook( $post_ID, $post );
}

/**
 * Triggers post procceses.
 */
function wpcf_admin_post_page_load_hook() {
    require_once WPCF_EMBEDDED_INC_ABSPATH . '/fields.php';
    require_once WPCF_EMBEDDED_INC_ABSPATH . '/fields-post.php';

    // Get post
    if ( isset( $_GET['post'] ) ) {
        $post_id = (int) $_GET['post'];
    } else if ( isset( $_POST['post_ID'] ) ) {
        $post_id = (int) $_POST['post_ID'];
    } else {
        $post_id = 0;
    }

    // Init processes
    if ( $post_id ) {
        $post = get_post( $post_id );
        if ( !empty( $post ) ) {
            wpcf_admin_post_init( $post );
        }
    } else {
        wpcf_admin_post_init();
    }
}

/**
 * Initiates/returns specific form.
 * 
 * @staticvar array $wpcf_forms
 * @param type $id
 * @param type $form
 * @return array 
 */
function wpcf_form( $id, $form = array() ) {
    static $wpcf_forms = array();
    if ( isset( $wpcf_forms[$id] ) ) {
        return $wpcf_forms[$id];
    }
    require_once WPCF_EMBEDDED_ABSPATH . '/classes/forms.php';
    $new_form = new Enlimbo_Forms_Wpcf();
    $new_form->autoHandle( $id, $form );
    $wpcf_forms[$id] = $new_form;
    return $wpcf_forms[$id];
}

/**
 * Add submit button, cancel button and help link to the popup.
 *
 */
function wpcf_form_popup_helper( $form, $submit_text = '', $cancel_text = '',
        $help = array() ) {
    if ( $submit_text ) {
        $form['submit'] = array(
            '#type' => 'submit',
            '#name' => 'submit',
            '#value' => $submit_text,
            '#attributes' => array('class' => 'button-primary'),
        );
    }
    if ( $cancel_text ) {
        $form['cancel'] = array(
            '#type' => 'button',
            '#name' => 'cancel',
            '#value' => $cancel_text,
            '#attributes' => array('class' => 'button-secondary',
                'onclick' => 'window.parent.jQuery(\'#TB_closeWindowButton\').click();return true;'),
            '#before' => ' ',
        );
    }
    if ( $help ) {
        $form = array_reverse( $form, true );
        $form['help'] = array(
            '#type' => 'markup',
            '#markup' => '<a class="wpcf-help-link" href="' . $help['url'] . '" target="_blank">' . $help['text'] . '</a>',
        );
        $form = array_reverse( $form, true );
    }

    return $form;
}

/**
 * Add optional items to the popup.
 *
 */
function wpcf_form_popup_add_optional( $form ) {

    $onclick = 'if (jQuery(\'#wpcf-popup-optionals\').is(\':visible\')) {
                        jQuery(this).html(\'' . __( 'Advanced',
                    'wpcf' ) . ' &raquo;\');
                        jQuery(\'#wpcf-popup-optionals\').hide();
                    } else {
                        jQuery(this).html(\'&laquo; ' . __( 'Hide advanced',
                    'wpcf' ) . '\');
                        jQuery(\'#wpcf-popup-optionals\').show();
                    }
                        ';

    $form['optional-start'] = array(
        '#type' => 'markup',
        '#markup' => '<br clear="both" /><a href="#" onclick="' . $onclick
        . '" class="wpcf-editor-popup-advanced-link">'
        . __( 'Advanced', 'wpcf' ) . ' &raquo;</a><br /><div id="wpcf-popup-optionals" style="margin-left:20px;display:none">',
    );

    $form['show_name'] = array(
        '#type' => 'checkbox',
        '#title' => __( 'Show the field name before the value', 'wpcf' ),
        '#name' => 'show_name',
        '#inline' => true,
        '#after' => '<br />',
        '#before' => '<br />',
    );

    $form['raw_mode'] = array(
        '#type' => 'checkbox',
        '#title' => __( 'Raw mode - Outputs exactly what is saved in the database',
                'wpcf' ),
        '#default_value' => '',
        '#name' => 'raw_mode',
        '#inline' => true,
        '#after' => '<br />',
    );

    $form['html_mode'] = array(
        '#type' => 'checkbox',
        '#title' => __( 'Wrap output in a div', 'wpcf' ),
        '#default_value' => '',
        '#name' => 'html_mode',
        '#inline' => true,
        '#after' => '<br />',
    );

    $form['css'] = array(
        '#type' => 'textfield',
        '#title' => '<td style="text-align:right;">' . __( 'CSS class:', 'wpcf' ) . '</td><td>',
        '#name' => 'css',
        '#value' => '',
        '#before' => '<br /><table><tr>',
        '#after' => '</td></tr>',
    );
    $form['style'] = array(
        '#type' => 'textfield',
        '#title' => '<td style="text-align:right;">' . __( 'CSS style:', 'wpcf' ) . '</td><td>',
        '#name' => 'style',
        '#value' => '',
        '#before' => '<tr>',
        '#after' => '</tr></table>'
    );
    $form['optional-end'] = array(
        '#type' => 'markup',
        '#markup' => '</div>',
    );


    return $form;
}

/**
 * Add the optional values from the popup to the shortcode.
 *
 */
function wpcf_fields_add_optionals_to_shortcode( $shortcode ) {

    if ( isset( $_POST['css'] ) && $_POST['css'] != '' ) {
        $shortcode = preg_replace( '/\[types([^\]]*)/',
                '$0 class="' . $_POST['css'] . '"', $shortcode );
    }
    if ( isset( $_POST['style'] ) && $_POST['style'] != '' ) {
        $shortcode = preg_replace( '/\[types([^\]]*)/',
                '$0 style="' . $_POST['style'] . '"', $shortcode );
    }

    if ( isset( $_POST['show_name'] ) && $_POST['show_name'] == '1' ) {
        $shortcode = preg_replace( '/\[types([^\]]*)/', '$0 show_name="true"',
                $shortcode );
    }


    if ( isset( $_POST['raw_mode'] ) && $_POST['raw_mode'] == '1' ) {
        $shortcode = preg_replace( '/\[types([^\]]*)/', '$0 raw="true"',
                $shortcode );
    }

    if ( isset( $_POST['html_mode'] ) && $_POST['html_mode'] == '1' ) {
        $shortcode = preg_replace( '/\[types([^\]]*)/', '$0 output="html"',
                $shortcode );
    }

    return $shortcode;
}

/**
 * Renders form elements.
 * 
 * @staticvar string $form
 * @param type $elements
 * @return type 
 */
function wpcf_form_simple( $elements ) {
    static $form = NULL;
    require_once WPCF_EMBEDDED_ABSPATH . '/classes/forms.php';
    if ( is_null( $form ) ) {
        $form = new Enlimbo_Forms_Wpcf();
    }
    return $form->renderElements( $elements );
}

/**
 * Validates form elements (simple).
 * 
 * @staticvar string $form
 * @param type $elements
 * @return type 
 */
function wpcf_form_simple_validate( &$elements ) {
    static $form = NULL;
    require_once WPCF_EMBEDDED_ABSPATH . '/classes/forms.php';
    if ( is_null( $form ) ) {
        $form = new Enlimbo_Forms_Wpcf();
    }
    $form->validate( $elements );
    return $form;
}

/**
 * Stores JS validation rules.
 * 
 * @staticvar array $validation
 * @param type $element
 * @return array 
 */
function wpcf_form_add_js_validation( $element ) {
    static $validation = array();
    if ( $element == 'get' ) {
        $temp = $validation;
        $validation = array();
        return $temp;
    }
    $validation[$element['#id']] = $element;
}

/**
 * Renders JS validation rules.
 * 
 * @global type $wpcf
 * @param type $selector Can be CSS class or element ID
 * @param type $echo
 * @return string
 */
function wpcf_form_render_js_validation( $selector = '.wpcf-form-validate',
        $echo = true ) {

    global $wpcf;

    /*
     * Since Types 1.1.5 we use Validation object.
     */
    $output = $wpcf->validation->js_fields( $selector );

    if ( $echo ) {
        echo $output;
    } else {
        return $output;
    }
}

/**
 * wpcf_custom_fields_to_be_copied
 *
 * Hook the copy custom fields from WPML and remove any of the fields
 * that wpcf will copy.
 */
function wpcf_custom_fields_to_be_copied( $copied_fields, $original_post_id ) {

    // see if this is one of our fields.
    $groups = wpcf_admin_post_get_post_groups_fields( get_post( $original_post_id ) );

    foreach ( $copied_fields as $id => $copied_field ) {
        foreach ( $groups as $group ) {
            foreach ( $group['fields'] as $field ) {
                if ( $copied_field == wpcf_types_get_meta_prefix( $field ) . $field['slug'] ) {
                    unset( $copied_fields[$id] );
                }
            }
        }
    }
    return $copied_fields;
}

/**
 * Holds validation messages.
 * 
 * @param type $method
 * @return type 
 */
function wpcf_admin_validation_messages( $method = false ) {
    $messages = array(
        'required' => __( 'This Field is required', 'wpcf' ),
        'email' => __( 'Please enter a valid email address', 'wpcf' ),
        'url' => __( 'Please enter a valid URL address', 'wpcf' ),
        'date' => __( 'Please enter a valid date', 'wpcf' ),
        'digits' => __( 'Please enter numeric data', 'wpcf' ),
        'number' => __( 'Please enter numeric data', 'wpcf' ),
        'alphanumeric' => __( 'Letters, numbers, spaces or underscores only please',
                'wpcf' ),
        'nospecialchars' => __( 'Letters, numbers, spaces, underscores and dashes only please',
                'wpcf' ),
        'rewriteslug' => __( 'Letters, numbers, slashes, underscores and dashes only please',
                'wpcf' )
    );
    if ( $method ) {
        return isset( $messages[$method] ) ? $messages[$method] : '';
    }
    return $messages;
}

/**
 * Adds admin notice.
 * 
 * @param type $message
 * @param type $class 
 */
function wpcf_admin_message( $message, $class = 'updated' ) {
    add_action( 'admin_notices',
            create_function( '$a=1, $class=\'' . $class . '\', $message=\''
                    . htmlentities( $message, ENT_QUOTES ) . '\'',
                    '$screen = get_current_screen(); if (!$screen->is_network) echo "<div class=\"message $class\"><p>" . html_entity_decode($message, ENT_QUOTES) . "</p></div>";' ) );
}

/**
 * Shows stored messages.
 */
function wpcf_show_admin_messages() {
    $messages = get_option( 'wpcf-messages', array() );
    $messages_for_user = isset( $messages[get_current_user_id()] ) ? $messages[get_current_user_id()] : array();
    $dismissed = get_option( 'wpcf_dismissed_messages', array() );
    if ( !empty( $messages_for_user ) && is_array( $messages_for_user ) ) {
        foreach ( $messages_for_user as $message_id => $message ) {
            if ( !in_array( $message['keep_id'], $dismissed ) ) {
                wpcf_admin_message( $message['message'], $message['class'] );
            }
            if ( empty( $message['keep_id'] )
                    || in_array( $message['keep_id'], $dismissed ) ) {
                unset( $messages[get_current_user_id()][$message_id] );
            }
        }
    }
    update_option( 'wpcf-messages', $messages );
}

/**
 * Stores admin notices if redirection is performed.
 * 
 * @param type $message
 * @param type $class
 * @return type 
 */
function wpcf_admin_message_store( $message, $class = 'updated',
        $keep_id = false ) {
    $messages = get_option( 'wpcf-messages', array() );
    $messages[get_current_user_id()][md5( $message )] = array(
        'message' => $message,
        'class' => $class,
        'keep_id' => $keep_id ? $keep_id : false,
    );
    update_option( 'wpcf-messages', $messages );
}

/**
 * Admin notice with dismiss button.
 * 
 * @param type $ID
 * @param string $message
 * @param type $store
 * @return boolean 
 */
function wpcf_admin_message_dismiss( $ID, $message, $store = true ) {
    $dismissed = get_option( 'wpcf_dismissed_messages', array() );
    if ( in_array( $ID, $dismissed ) ) {
        return false;
    }
    $message = $message . '<div style="float:right; margin:-15px 0 0 15px;"><a onclick="jQuery(this).parent().parent().fadeOut();jQuery.get(\''
            . admin_url( 'admin-ajax.php?action=wpcf_ajax&amp;wpcf_action=dismiss_message&amp;id='
                    . $ID . '&amp;_wpnonce=' . wp_create_nonce( 'dismiss_message' ) ) . '\');return false;"'
            . 'class="button-secondary" href="javascript:void(0);">'
            . __( 'Dismiss', 'wpcf' ) . '</a></div>';
    if ( $store ) {
        wpcf_admin_message_store( $message, 'updated', $ID );
    } else {
        wpcf_admin_message( $message );
    }
}

/**
 * Adds dismissed message to record.
 * 
 * @param type $ID 
 */
function wpcf_admin_message_set_dismissed( $ID ) {
    $messages = get_option( 'wpcf_dismissed_messages', array() );
    if ( !in_array( $ID, $messages ) ) {
        $messages[] = $ID;
        update_option( 'wpcf_dismissed_messages', $messages );
    }
}

/**
 * Removes dismissed message from record.
 * 
 * @param type $ID 
 */
function wpcf_admin_message_restore_dismissed( $ID ) {
    $messages = get_option( 'wpcf_dismissed_messages', array() );
    $key = array_search( $ID, $messages );
    if ( $key !== false ) {
        unset( $messages[$key] );
        update_option( 'wpcf_dismissed_messages', $messages );
    }
}

/**
 * Saves cookie.
 * 
 * @param type $data 
 */
function wpcf_cookies_add( $data ) {
    if ( isset( $_COOKIE['wpcf'] ) ) {
        $data = array_merge( (array) $_COOKIE['wpcf'], $data );
    }
    setcookie( 'wpcf', $data, time() + $lifetime, COOKIEPATH, COOKIE_DOMAIN );
}

/**
 * Renders page head.
 * 
 * @see WPCF_Template::ajax_header()
 * @global type $pagenow
 * @param type $title
 */
function wpcf_admin_ajax_head( $title ) {

    /*
     * Since Types 1.2 and WP 3.5
     * AJAX head is rendered differently
     */
    global $wp_version, $wpcf;
    if ( version_compare( $wp_version, '3.4', '>' ) ) {
        // WP Header
        echo $wpcf->template->ajax_header();
        return true;
    }

    global $pagenow;
    $hook_suffix = $pagenow;

    ?>
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml" <?php do_action( 'admin_xml_ns' ); ?> <?php language_attributes(); ?>>
        <head>
            <meta http-equiv="Content-Type" content="<?php bloginfo( 'html_type' ); ?>; charset=<?php echo get_option( 'blog_charset' ); ?>" />
            <title><?php echo $title; ?></title>
            <?php
            if ( wpcf_compare_wp_version( '3.2.1', '<=' ) ) {
                wp_admin_css( 'global' );
            }
            wp_admin_css();
            wp_admin_css( 'colors' );
            wp_admin_css( 'ie' );
//            do_action('admin_enqueue_scripts', $hook_suffix);
            do_action( "admin_print_styles-$hook_suffix" );
            do_action( 'admin_print_styles' );
//            do_action("admin_print_scripts-$hook_suffix");
            do_action( 'admin_print_scripts' );
//            do_action("admin_head-$hook_suffix");
//            do_action('admin_head');
            do_action( 'admin_head_wpcf_ajax' );

            ?>
            <style type="text/css">
                html { height: auto; }
            </style>

            <script type="text/javascript">
                // <![CDATA[
                jQuery(document).ready(function(){
                    // Position the help link in the title bar.
                    var title = jQuery('#TB_closeAjaxWindow', window.parent.document);
                    if (title.length != 0) {
                        title.after(jQuery('.wpcf-help-link'));
                    }
                });
                // ]]>
            </script>

            <link rel="stylesheet" href="<?php echo WPCF_EMBEDDED_RES_RELPATH . '/css/basic.css'; ?>" type="text/css" media="all" />

        </head>
        <body style="padding: 20px;">
            <?php
        }

        /**
         * Renders page footer
         * 
         * @see WPCF_Template::ajax_footer()
         */
        function wpcf_admin_ajax_footer() {

            /*
             * Since Types 1.2 and WP 3.5
             * AJAX footer is rendered differently
             */
            global $wp_version, $wpcf;
            if ( version_compare( $wp_version, '3.4', '>' ) ) {
                // WP Footer
                $wpcf->template->ajax_footer();
                return true;
            }

            global $pagenow;
            do_action( 'admin_footer_wpcf_ajax' );
//    do_action('admin_footer', '');
//    do_action('admin_print_footer_scripts');
//    do_action("admin_footer-" . $pagenow);

            ?>
        </body>
    </html>

    <?php
}

/**
 * Gets var from $_SERVER['HTTP_REFERER'].
 * 
 * @param type $var 
 */
function wpcf_admin_get_var_from_referer( $var ) {
    $value = false;
    if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
        $parts = explode( '?', $_SERVER['HTTP_REFERER'] );
        if ( !empty( $parts[1] ) ) {
            parse_str( $parts[1], $vars );
            if ( isset( $vars[$var] ) ) {
                $value = $vars[$var];
            }
        }
    }
    return $value;
}

/**
 * Adds JS settings.
 * 
 * @staticvar array $settings
 * @param type $id
 * @param type $setting
 * @return string 
 */
function wpcf_admin_add_js_settings( $id, $setting = '' ) {
    static $settings = array();
    $settings['wpcf_nonce_ajax_callback'] = '\'' . wp_create_nonce( 'execute' ) . '\'';
    $settings['wpcf_cookiedomain'] = '\'' . COOKIE_DOMAIN . '\'';
    $settings['wpcf_cookiepath'] = '\'' . COOKIEPATH . '\'';
    if ( $id == 'get' ) {
        $temp = $settings;
        $settings = array();
        return $temp;
    }
    $settings[$id] = $setting;
}

/**
 * Renders JS settings.
 * 
 * @return type 
 */
function wpcf_admin_render_js_settings() {
    $settings = wpcf_admin_add_js_settings( 'get' );
    if ( empty( $settings ) ) {
        return '';
    }

    ?>
    <script type="text/javascript">
        //<![CDATA[
    <?php
    foreach ( $settings as $id => $setting ) {
        if ( is_string( $setting ) ) {
            $setting = trim( $setting, '\'' );
            $setting = "'" . esc_js( $setting ) . "'";
        } else {
            $setting = intval( $setting );
        }
        echo 'var ' . $id . ' = ' . $setting . ';' . "\r\n";
    }

    ?>
        //]]>
    </script>
    <?php
}

/**
 * wpcf_get_fields
 *
 * returns the fields handled by types
 *
 */
function wpcf_get_post_meta_field_names() {
    require_once WPCF_EMBEDDED_INC_ABSPATH . '/fields.php';
    $fields = wpcf_admin_fields_get_fields();

    $field_names = array();
    foreach ( $fields as $field ) {
        $field_names[] = wpcf_types_get_meta_prefix( $field ) . $field['slug'];
    }

    return $field_names;
}

/**
 * Forces 'Insert into post' link when called from our WYSIWYG.
 * 
 * @param array $args
 * @return boolean 
 */
function wpcf_get_media_item_args_filter( $args ) {
    if ( strpos( $_SERVER['SCRIPT_NAME'], '/media-upload.php' ) === false ) {
        return $args;
    }
    if ( !empty( $_COOKIE['wpcfActiveEditor'] )
            && strpos( $_COOKIE['wpcfActiveEditor'], 'wpcf-wysiwyg-' ) !== false ) {
        $args['send'] = true;
    }
    return $args;
}

/**
 * Gets post.
 * 
 * @return type 
 */
function wpcf_admin_get_edited_post() {
    if ( isset( $_GET['post'] ) ) {
        $post_id = (int) $_GET['post'];
    } else if ( isset( $_POST['post_ID'] ) ) {
        $post_id = (int) $_POST['post_ID'];
    } else {
        $post_id = 0;
    }
    if ( $post_id ) {
        return get_post( $post_id );
    } else {
        return array();
    }
}

/**
 * Gets post type.
 * 
 * @param type $post
 * @return boolean 
 */
function wpcf_admin_get_post_type( $post ) {
    if ( $post ) {
        $post_type = get_post_type( $post );
    } else {
        if ( !isset( $_GET['post_type'] ) ) {
            $post_type = 'post';
        } else if ( in_array( $_GET['post_type'],
                        get_post_types( array('show_ui' => true) ) ) ) {
            $post_type = $_GET['post_type'];
        } else {
            $post_type = 'post';
        }
    }
    return $post_type;
}
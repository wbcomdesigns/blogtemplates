<?php

if ( ! class_exists( 'blog_templates' ) ) {

    class blog_templates {

        /**
        * PHP 5 Constructor
        *
        * @since 1.0
        */
        
        function __construct() {

            if ( is_network_admin() ) {
                new blog_templates_main_menu();

                if ( apply_filters( 'nbt_activate_categories_feature', true ) )
                    new blog_templates_categories_menu();
                
                new blog_templates_settings_menu();
            }

            $model = nbt_get_model();
            $categories_count = $model->get_categories_count();
            if ( empty( $categories_count ) ) {
                $model->add_default_template_category();
            }

            add_action( 'init', array( &$this, 'maybe_upgrade' ) );


            // Actions
            $action_order = defined('NBT_APPLY_TEMPLATE_ACTION_ORDER') && NBT_APPLY_TEMPLATE_ACTION_ORDER ? NBT_APPLY_TEMPLATE_ACTION_ORDER : 9999;
            add_action('wpmu_new_blog', array($this, 'set_blog_defaults'), apply_filters('blog_templates-actions-action_order', $action_order), 6); // Set to *very high* so this runs after every other action; also, accepts 6 params so we can get to meta
            add_action('admin_footer', array($this,'add_template_dd'));

            add_action('wp_enqueue_scripts', create_function('', 'wp_enqueue_script("jquery");'));

            // Special features for Multi-Domains
            add_action( 'add_multi_domain_form_field', array($this, 'multi_domain_form_field' ) ); // add field to domain addition form
            add_action( 'edit_multi_domain_form_field', array($this, 'multi_domain_form_field' ) ); // add field to domain edition form
            add_filter( 'md_update_domain', array($this, 'multi_domain_update_domain' ), 10, 2 ); // saves blog template value on domain update
            add_filter( 'manage_multi_domains_columns', array($this, 'manage_multi_domains_columns' ) ); // add column to multi domain table
            add_action( 'manage_multi_domains_custom_column', array($this, 'manage_multi_domains_custom_column' ), 10, 2 ); // populate blog template column in multi domain table
            
            // Signup: WordPress            
            add_action( 'signup_hidden_fields', array( &$this, 'maybe_add_template_hidden_field' ) );
            add_action( 'signup_blogform', array( $this, 'registration_template_selection' ) );
            add_filter( 'add_signup_meta', array( $this, 'registration_template_selection_add_meta' ) );

            // Signup: BuddyPress
            add_action( 'bp_blog_details_fields', array( &$this, 'maybe_add_template_hidden_field' ) );
            add_action('bp_after_blog_details_fields', array($this, 'registration_template_selection'));
            add_filter('bp_signup_usermeta', array($this, 'registration_template_selection_add_meta'));
            add_action( 'bp_before_blog_details_fields', 'nbt_bp_add_register_scripts' );

            /**
             * From 1.7.1 version we are not allowing to template the main site
             * This will alert the user to remove that template
             */
            add_action( 'all_admin_notices', array( &$this, 'alert_main_site_templated' ) );

            add_action( 'delete_blog', array( &$this, 'maybe_delete_template' ), 10, 1 );

            add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_styles' ) );


            do_action( 'nbt_object_create', $this );

        }

        public function enqueue_styles() {
            global $wp_version;
            
            if ( version_compare( $wp_version, '3.8', '>=' ) ) {
                wp_enqueue_style( 'mcc-icons', NBT_PLUGIN_URL . 'blogtemplatesfiles/assets/css/icons-38.css' );
            }
            else {
                wp_enqueue_style( 'mcc-icons', NBT_PLUGIN_URL . 'blogtemplatesfiles/assets/css/icon-styles.css' );
            }
        }

        function maybe_upgrade() {

            // Split posts option into posts and pages options
            $saved_version = get_site_option( 'nbt_plugin_version', false );

            if ( ! $saved_version )
                $saved_version = '1.7.2';

            if ( $saved_version == NBT_PLUGIN_VERSION )
                return;

            require_once( NBT_PLUGIN_DIR . 'blogtemplatesfiles/upgrade.php' );

            if ( version_compare( $saved_version, '1.7.2', '<=' ) ) {
                $options = get_site_option( 'blog_templates_options', array( 'templates' => array() ) );
                $new_options = $options;
                foreach ( $options['templates'] as $key => $template ) {
                    $to_copy = $template['to_copy'];
                    if ( in_array( 'posts', $to_copy ) )
                        $new_options['templates'][ $key ]['to_copy'][] = 'pages';
                }

                update_site_option( 'blog_templates_options', $new_options );
                update_site_option( 'nbt_plugin_version', '1.7.2' );
            }
            

            if ( version_compare( $saved_version, '1.7.6', '<' ) ) {
                $options = get_site_option( 'blog_templates_options', array( 'templates' => array() ) );
                $new_options = $options;

                foreach ( $options['templates'] as $key => $template ) {
                    $new_options['templates'][ $key ]['block_posts_pages'] = false;
                    $new_options['templates'][ $key ]['post_category'] = array( 'all-categories' );
                }
                
                update_site_option( 'blog_templates_options', $new_options );
                update_site_option( 'nbt_plugin_version', '1.7.6' );
            }


            if ( version_compare( $saved_version, '1.9', '<' ) ) {
                $model = nbt_get_model();
                $model->create_tables();
                blog_templates_upgrade_19();
                update_site_option( 'nbt_plugin_version', '1.9' );
            }

            if ( version_compare( $saved_version, '1.9.1', '<' ) ) {
                blog_templates_upgrade_191();
                update_site_option( 'nbt_plugin_version', '1.9.1' );
            }

            if ( version_compare( $saved_version, '2.0', '<' ) ) {
                $model = nbt_get_model();
                $model->create_tables();

                // Due to a server issue in WPMUDEV we need to upgrade again in the same way
                blog_templates_upgrade_191();

                blog_templates_upgrade_20();
                update_site_option( 'nbt_plugin_version', '2.0' );
            }

            if ( version_compare( $saved_version, '2.2', '<' ) ) {
                blog_templates_upgrade_22();
                update_site_option( 'nbt_plugin_version', '2.2' );   
            }

            if ( version_compare( $saved_version, '2.6.2', '<' ) ) {
                blog_templates_upgrade_262();
                update_site_option( 'nbt_plugin_version', '2.6.2' );   
            }

        }

        /**
         * Delete templates attached to blogs that no longer exist
         * 
         * @param Integer $blog_id 
         */
        function maybe_delete_template( $blog_id ) {

            $delete_template_ids = array();
            $settings = nbt_get_settings();

            // Searching those templates attached to that blog
            foreach ( $settings['templates'] as $key => $template ) {
                if ( $template['blog_id'] == $blog_id ) {
                    $delete_template_ids[] = $key;
                }
            }

            // Deleting and saving new options
            if ( ! empty( $delete_template_ids ) ) {
                $model = nbt_get_model();
                foreach ( $delete_template_ids as $template_id ) {
                    unset( $settings['templates'][ $template_id ] );

                    if ( $settings['default'] == $template_id )
                        $settings['default'] = false;

                    $model->delete_template( $template_id );

                    do_action( 'blog_templates_delete_template', $template_id );

                    nbt_update_settings( $settings );
                }
            }
        }

        function alert_main_site_templated() {
            $settings = nbt_get_settings();
            if ( ! empty( $settings['templates'] ) ) {
                $main_site_templated = false;
                foreach ( $settings['templates'] as $template ) {
                    if ( is_main_site( absint( $template['blog_id'] ) ) )
                        $main_site_templated = true;
                }

                if ( $main_site_templated && is_super_admin() ) {
                    $settings_url = add_query_arg( 'page', 'blog_templates_main', network_admin_url( 'settings.php' ) );
                    ?>
                        <div class="error">
                            <p><?php printf( __( '<strong>New Blog Templates alert:</strong> The main site cannot be templated from 1.7.1 version, please <a href="%s">go to settings page</a> and remove that template (will not be shown as a choice from now on)', 'blog_templates' ), $settings_url ); ?></p>
                        </div>
                    <?php
                }
            }
        }

        /**
        * Returns a dropdown of all blog templates
        *
        * @since 1.0
        */
        function get_template_dropdown( $tag_name, $include_none, $echo = true, $esc_js = true ) {

            $settings = nbt_get_settings();
            $templates = array();
            foreach ( $settings['templates'] as $key => $template ) {
                if ( ! is_main_site( absint( $template['blog_id'] ) ) )
                    $templates[$key] = $template['name'];
            }

            $selector = '';
            if ( is_array( $templates ) ) {
                $selector .= '<select name="' . esc_attr( $tag_name ) . '">';
                if ( $include_none )
                    $selector .= '<option value="none">' . __( 'None', 'blog_templates' ) . '</option>';
                
                foreach ( $templates as $key => $value ) {
                    $label = ( $esc_js ) ? esc_js( $value ) : stripslashes_deep( $value );
                    $selector .= '<option value="' . esc_attr( $key ) . '" ' . esc_attr( selected( $key == $settings['default'], true, false ) ) . '>' . $label . '</option>';
                }
                $selector .= '</select>';    

            }

            if ( $echo )
                echo $selector;
            else
                return $selector;
        }

        /**
        * Adds the Template dropdown to the WPMU New Blog form
        *
        * @since 1.0
        */
        function add_template_dd() {
            global $pagenow;
            if( ! in_array( $pagenow, array( 'ms-sites.php', 'site-new.php' ) ) || isset( $_GET['action'] ) && 'editblog' == $_GET['action'] )
                return;

            ?>
            <script type="text/javascript">
                jQuery(document).ready(function() {
                    jQuery('.form-table:last tr:last').before('\
                    <tr class="form-field form-required">\
                        <th scope="row"><?php _e('Template', 'blog_templates') ?></th>\
                        <td><?php $this->get_template_dropdown('blog_template_admin',true); ?></td>\
                    </tr>');
                });
            </script>
            <?php
        }


        /**
        * Checks for a template to use, and if it exists, copies the templated settings to the new blog
        *
        * @param mixed $blog_id
        * @param mixed $user_id
        *
        * @since 1.0
        */
        function set_blog_defaults( $blog_id, $user_id, $_passed_domain=false, $_passed_path=false, $_passed_site_id=false, $_passed_meta=false ) {
            global $wpdb, $multi_dm;

            $settings = nbt_get_settings();

            $default = false;
            
            /* Start special Multi-Domain feature */
            if( !empty( $multi_dm ) ) {
                $bloginfo = get_blog_details( (int) $blog_id, false );
                foreach( $multi_dm->domains as $multi_domain ) {
                    if( strpos( $bloginfo->domain, $multi_domain['domain_name'] ) ) {
                        if( isset( $multi_domain['blog_template'] ) && !empty( $settings['templates'][$multi_domain['blog_template']] ) )
                            $default = $settings['templates'][$multi_domain['blog_template']];
                    }
                }
            }
            /* End special Multi-Domain feature */

            if( empty( $default ) && isset( $settings['default'] ) && is_numeric( $settings['default'] ) ) { // select global default
                $default = isset($settings['templates'][$settings['default']]) 
                    ? $settings['templates'][$settings['default']]
                    : false
                ;
            }

            
            $template = '';
            // Check $_POST first for passed template and use that, if present.
            // Otherwise, check passed meta from blog signup.
            // Lastly, apply the default.
            if ( isset( $_POST['blog_template_admin'] ) && is_network_admin() ) {
                // The blog is being created from the admin network.
                // The super admin can create a blog without a template
                if ( 'none' === $_POST['blog_template_admin'] ) {
                    // The Super Admin does not want to use any template
                    return;
                }
                else {
                    $template = $settings['templates'][$_POST['blog_template_admin']];
                }
            }
            elseif ( isset( $_POST['blog_template'] ) && is_numeric( $_POST['blog_template'] ) ) { //If they've chosen a template, use that. For some reason, when PHP gets 0 as a posted var, it doesn't recognize it as is_numeric, so test for that specifically
                $template = $settings['templates'][$_POST['blog_template']];
            } elseif ($_passed_meta && isset($_passed_meta['blog_template']) && is_numeric($_passed_meta['blog_template'])) { // Do we have a template in meta?
                $template = $settings['templates'][$_passed_meta['blog_template']]; // Why, yes. Yes, we do. Use that. 
            } elseif ( $default ) { //If they haven't chosen a template, use the default if it exists
                $template = $default;
            }
            $template = apply_filters('blog_templates-blog_template', $template, $blog_id, $user_id );
            if ( ! $template || 'none' == $template )
                return; //No template, lets leave

            switch_to_blog( $blog_id ); //Switch to the blog that was just created            

            include( 'copier.php' );

            $copier_args = array();
            foreach( $template['to_copy'] as $value ) {
                $copier_args['to_copy'][ $value ] = true;
            }
            $copier_args['post_category'] = $template['post_category'];
            $copier_args['pages_ids'] = $template['pages_ids'];
            $copier_args['template_id'] = $template['ID'];
            $copier_args['block_posts_pages'] = $template['block_posts_pages'];
            $copier_args['update_dates'] = $template['update_dates'];
            $copier_args['additional_tables'] = ( isset( $template['additional_tables'] ) && is_array( $template['additional_tables'] ) ) ? $template['additional_tables'] : array();

            $copier = new NBT_Template_copier( $template['blog_id'], $blog_id, $user_id, $copier_args );
            $copier->execute();

            restore_current_blog(); //Switch back to our current blog

        }


        /**
        * Adds field for Multi Domain addition and edition forms
        *
        * @since 1.2
        */
        function multi_domain_form_field( $domain = '' ) {

            $settings = nbt_get_settings();
            if( count( $settings['templates'] ) <= 1 ) // don't display field if there is only one template or none
                return false;
            ?>
            <tr>
                <th scope="row"><label for="blog_template"><?php _e( 'Default Blog Template', 'blog_templates' ) ?>:</label></th>
                <td>
                    <select id="blog_template" name="blog_template">
                        <option value="">Default</option>
                        <?php
                        foreach( $settings['templates'] as $key => $blog_template ) {
                            $selected = isset( $domain['blog_template'] ) ? selected( $key, $domain['blog_template'], false ) : '';
                            echo "<option value='$key'$selected>$blog_template[name]</option>";
                        }
                        ?>
                    </select><br />
                    <span class="description"><?php _e( 'Default Blog Template used for this domain.', 'blog_templates' ) ?></span>
                </td>
            </tr>
            <?php
        }

       

        

        /**
        * Save Blog Template value in the current domain array
        *
        * @since 1.2
        */
        function multi_domain_update_domain( $current_domain, $domain ) {
            $current_domain['blog_template'] = isset( $domain['blog_template'] ) ? $domain['blog_template'] : '';

            return $current_domain;
        }

        /**
        * Adds Blog Template column to Multi-Domains table
        *
        * @since 1.2
        */
        function manage_multi_domains_columns( $columns ) {
            $columns['blog_template'] = __( 'Blog Template', 'blog_templates' );
            return $columns;
        }

        /**
        * Display content of the Blog Template column in the Multi-Domains table
        *
        * @since 1.2
        */
        function manage_multi_domains_custom_column( $column_name, $domain ) {
            if( 'blog_template' == $column_name ) {
                $settings = nbt_get_settings();
                if( !isset( $domain['blog_template'] ) ) {
                    echo 'Default';
                } elseif( !is_numeric( $domain['blog_template'] ) ) {
                    echo 'Default';
                } else {
                    $key = $domain['blog_template'];
                    echo $settings['templates'][$key]['name'];
                }
            }
        }

        

        
        function maybe_add_template_hidden_field() {
            $settings = nbt_get_settings();
            if ( 'page_showcase' == $settings['registration-templates-appearance'] ) {
                if ( 'just_user' == $_REQUEST['blog_template'] ) {
                    ?>
                        <input type="text" name="blog_template" value="just_user">
                        <script>
                            jQuery(document).ready(function($) {
                                $('#signupuser').attr('checked', true);
                                $('#signupblog').hide();
                                $('label[for="signupblog"]').hide();
                                $('#blog-details-section').hide();
                            });
                        </script>
                    <?php
                }
                else {
                    $value = isset( $_REQUEST['blog_template'] ) ? $_REQUEST['blog_template'] : '';
                    ?>
                        <input type="hidden" name="blog_template" value="<?php echo absint( $_REQUEST['blog_template'] ); ?>">
                    <?php
                }
                return;
            }
        }
        /**
         * Shows template selection on registration.
         */
        function registration_template_selection () {
            $settings = nbt_get_settings();

            if ( ! $settings['show-registration-templates'] ) 
                return false;


            // Setup vars
            $templates = $settings['templates'];

            $templates_to_remove = array();
            foreach ( $templates as $key => $template ) {

                if ( is_main_site( $template['blog_id'] ) )
                    $templates_to_remove[] = $key;
            }

            if ( ! empty( $templates_to_remove ) ) {
                foreach ( $templates_to_remove as $key )
                    unset( $templates[ $key ] );
            }


            $tpl_file_suffix = $settings['registration-templates-appearance'] ? '-' . $settings['registration-templates-appearance'] : '';
            $tpl_file = "blog_templates-registration{$tpl_file_suffix}.php";


            // Setup theme file
            $theme_file = locate_template( array( $tpl_file ) );
            $theme_file = $theme_file ? $theme_file : NBT_PLUGIN_DIR . '/blogtemplatesfiles/template/' . $tpl_file;

            if ( ! file_exists( $theme_file ) ) 
                return false;

            nbt_render_theme_selection_scripts( $settings );

            $templates = apply_filters( 'nbt_signup_templates', $templates );

            @include $theme_file;

        }
        
        /**
         * Store selected template in blog meta on signup.
         */
        function registration_template_selection_add_meta ($meta) {
            $meta = $meta ? $meta : array();
            $settings = nbt_get_settings();
            $meta['blog_template'] = isset( $_POST['blog_template'] ) && is_numeric( $_POST['blog_template'] ) ? $_POST['blog_template'] : $settings['default'];
            return $meta;
        }

        

    } // End Class

    // instantiate the class
    global $blog_templates;
    $blog_templates = new blog_templates();

} // End if blog_templates class exists statement



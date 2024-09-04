<?php

/**
 * Plugin Name: ITL Movies Filter
 * Author: ITLinked
 * Version: 1.0
 * License: GPLv2 or later
 * Description: Custom Movies Filter System for the website.
 */

defined('ABSPATH') || exit;

/**
 * WC_Admin_Importers Class.
 */
class WP_ITL_MOVIES_FILTER
{

    public static $directory_path;
    public static $directory_url;
    public static $products_arr = array();
    public static $plugin_basename = ''; // Values set at function `set_plugin_vars`

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->set_plugin_vars();
        $this->hooks();
        $this->include_shorcodes();
        $this->register_styles();
    }

    /**
     * Include Hooks.
     */
    public function hooks()
    {
        //Ajax Callback Functions
        add_action('wp_ajax_get_movies_data', array($this, 'get_movies_data'));
        add_action('wp_ajax_nopriv_get_movies_data', array($this, 'get_movies_data'));

        add_action('wp_ajax_get_fields_data', array($this, 'get_fields_data'));
        add_action('wp_ajax_nopriv_get_fields_data', array($this, 'get_fields_data'));

        add_action('wp_ajax_get_cast_data', array($this, 'get_cast_data'));
        add_action('wp_ajax_nopriv_get_cast_data', array($this, 'get_cast_data'));

        add_action('wp_ajax_get_search_data', array($this, 'get_search_data'));
        add_action('wp_ajax_nopriv_get_search_data', array($this, 'get_search_data'));

        add_action('wp_ajax_itl_get_search_movies', array($this, 'itl_get_search_movies'));
        add_action('wp_ajax_nopriv_itl_get_search_movies', array($this, 'itl_get_search_movies'));

        add_action('wp_ajax_itl_get_hash_comments', array($this, 'itl_get_hash_comments'));
        add_action('wp_ajax_nopriv_itl_get_hash_comments', array($this, 'itl_get_hash_comments'));

        add_action('wp_ajax_load_more_posts', array($this, 'load_more_posts'));
        add_action('wp_ajax_nopriv_load_more_posts', array($this, 'load_more_posts'));
        add_action('admin_menu', array($this, 'itl_product_update_amazon'));
        add_action('wp_ajax_itl_loop_through_all_movies', array($this, 'itl_loop_through_all_movies'));
        add_action('wp_ajax_nopriv_itl_loop_through_all_movies', array($this, 'itl_loop_through_all_movies'));

        // add_action('init', array($this, 'register_custom_taxonomies'), 0);


        add_action('dtcast_add_form_fields', array($this, 'add_taxonomy_image_field'), 10, 2);
        add_action('dtcast_edit_form_fields', array($this, 'edit_taxonomy_image_field'), 10, 2);

        add_action('edited_dtcast', array($this, 'save_taxonomy_image_field'), 10, 2);
        add_action('create_dtcast', array($this, 'save_taxonomy_image_field'), 10, 2);
        // add_action('tour_archive_image', array($this, 'display_taxonomy_image'));


        add_filter("manage_edit-dtcast_columns", array($this, "add_taxonomy_image_column"));


        add_filter("manage_dtcast_custom_column", array($this, "display_taxonomy_image_column"), 10, 3);

        // Hook your custom function to the 'comment_post' action hook
        add_action('comment_post', array($this, 'itl_update_comment'), 10, 2);
        add_action('wp_set_comment_status', array($this, 'itl_update_comment'), 10, 2);
    }

    // Add custom column to taxonomy term admin list
    function add_taxonomy_image_column($columns)
    {
        $columns['cast_image'] = __('Cast Image', 'textdomain');
        return $columns;
    }

    // Display custom column content for taxonomy term
    function display_taxonomy_image_column($content, $column_name, $term_id)
    {
        if ($column_name === 'cast_image') {
            // Get the image URL saved in the term meta
            $image_url = get_term_meta($term_id, 'cast-image', true);

            // Display the image if available
            if (!empty($image_url)) {
                $content .= '<img src="' . esc_url($image_url) . '" style="max-width: 50px; max-height: 50px;" />';
            }
        }
        return $content;
    }

    function itl_product_update_amazon()
    {
        add_menu_page(
            'Update Movies Filter',           // Page title
            'Update Movies Content',           // Menu title
            'manage_options',       // Capability required to access the menu
            'itl-tab',           // Menu slug
            array($this, 'itl_tab_page'),      // Callback function to display content
            'dashicons-admin-page', // Icon for the menu
            30                      // Menu position
        );
    }


    function itl_tab_page()
    {
        wp_enqueue_script('itl-search-js');
        wp_enqueue_style('itl-filter-css');
        $localize = array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            // 'nonce' => wp_create_nonce('itl_movies_filter_nonce')
        );
        wp_localize_script('itl-search-js', 'itlObj', $localize);
        echo '<div class="wrap">';
        echo '<h5 id="itl_message" style="text-color: green"></h5>';
        echo '<h2>Update Movies Filter Data</h2>';
        echo '<div style="display: flex"><button class="itl-button button-primary">Update</button><div id="itl_loader" style="display: none"><i class="fa fa-spin fa-spinner"></i></div></div>';
        echo '</div>';
    }

    function itl_loop_through_all_movies()
    {


        self::$products_arr = array();

        $args = array(
            'post_type' => array('tvshows', 'movies'),
            'post_status' => 'publish',
            'posts_per_page' => 5000,
            'order' => 'DESC', // Descending order (latest first)
            'orderby' => 'date',
        );

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $p_id = get_the_ID();


                $this->itl_update_postmeta($p_id);
            }
            wp_reset_postdata();
        }

        echo json_encode(
            array(
                'products_updated' => self::$products_arr,
                'success' => true,
            )
        );
        wp_die();
    }


    function itl_update_postmeta($post_id)
    {

        $content = get_post_field('post_content', $post_id, true);
        $content = wp_strip_all_tags($content);
        $title = html_entity_decode(get_the_title());
        global $wpdb;
        // Fetch comments for the current post
        $comments = get_comments(
            array(
                'post_id' => $post_id,
                'status' => 'approve', // You can adjust the status as needed
            )
        );

        // Check if there are comments
        $com = '';
        if ($comments) {
            // Loop through each comment
            foreach ($comments as $comment) {
                // Access comment information, e.g., $comment->comment_content, $comment->comment_author, etc.
                $comment_des = esc_html($comment->comment_content);
                $com .= $comment_des;
            }
        }
        update_post_meta($post_id, 'itl_post_content', $content);
        update_post_meta($post_id, 'itl_post_comment', $com);
        update_post_meta($post_id, 'itl_post_title', $title);
    }

    function itl_update_comment($comment_ID, $comment_approved)
    {
        // Get the post ID associated with the comment
        $com = get_comment($comment_ID);
        $post_id = $com->comment_post_ID;
        // Call your custom function with the post ID
        $this->itl_update_postmeta($post_id);
    }

    // Define your custom function to be called when a comment is approved
    function itl_update_comment2($comment_ID, $comment)
    {
        // Get the post ID associated with the comment
        $post_id = $comment->comment_post_ID;
        print_r($post_id);
        exit;
        // Call your custom function with the post ID
        $this->itl_update_postmeta($post_id);
    }

    /**
     * Define plugin variables.
     */
    public function set_plugin_vars()
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        self::$directory_path = plugin_dir_path(__FILE__);
        self::$directory_url = plugin_dir_url(__FILE__);
        self::$plugin_basename = plugin_basename(__FILE__);
    }

    /**
     * Include Shortcodes.
     */
    public function include_shorcodes()
    {
        //Shortcode
        add_shortcode('itl_movies_filter', array($this, 'itl_filter_shortcode'));
        add_shortcode('itl_search_results', array($this, 'itl_search_results_shortcode'));
    }


    /**
     * Register_styles.
     */
    public function register_styles()
    {
        // files
        wp_register_style('itl-filter-css', self::$directory_url . 'css/itl_filter.css', array(), microtime());
        wp_register_style('bootstrap-cpt', 'https://cdn.jsdelivr.net/npm/bootstrap@4.4.1/dist/css/bootstrap.min.css');



        wp_register_script('popper', 'https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js', array('jquery'), null, true);
        wp_register_script('itl-filter-js', self::$directory_url . 'js/itl_filter.js', array('jquery'), microtime(), true);
        wp_register_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@4.4.1/dist/js/bootstrap.min.js', array('jquery'), null, true);
        wp_register_script('vue-js', 'https://cdn.jsdelivr.net/npm/vue/dist/vue.js', array('jquery'), null, true);
        wp_register_script('itl-search-js', self::$directory_url . 'js/itl_search.js', array('jquery'), microtime(), true);
        //   wp_enqueue_script('itl-search-js');
    }


    /**
     * Enqueue_Files.
     */
    public function enqueue_files()
    {
        // files
        wp_enqueue_style('bootstrap-cpt');
        wp_enqueue_style('itl-filter-css');

        wp_enqueue_script('popper');
        wp_enqueue_script('bootstrap-js');
        wp_enqueue_script('vue-js');
        wp_enqueue_script('itl-filter-js');

        $localize = array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            // 'nonce' => wp_create_nonce('itl_movies_filter_nonce')
        );
        wp_localize_script('itl-filter-js', 'itlObj', $localize);
    }

    function itl_filter_shortcode($atts)
    {

        $this->enqueue_files();
        //        $page = isset($atts["search_page"]) ? true : false;
        //         $post_id = 178421;
        // $term_id = 65794;  // Replace with the term ID you want to assign
        // $taxonomy = 'xitlnetworks';  // Replace with the actual taxonomy name

        // Assign the term to the post
        // wp_set_post_terms($post_id, $term_id, $taxonomy);
        ob_start();
?>
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
<div id="itlItemFilter" v-cloak>
    <div v-if="mainLoader" class="lds-ellipsis main-loader">
        <div></div>
        <div></div>
        <div></div>
        <div></div>
    </div>
    <button v-if="!seeResult" @click="sResult()" :disabled="!seeResultDis" class="btn btn-st-1">See Results</button>
    <div class="itl-chips" v-if="seeResult">
        <button v-for="(sa,sind) in sAllData" :key="sind" @click="removeItem(sa)" class="itl-btn round w-close">
            <span v-if="sa.key == 'title' || sa.key == 'rating'">{{sa.key}} <span
                    class="sp-2">:</span>{{sa.value}}</span>
            <span v-if="sa.key != 'title' && sa.key != 'rating'">{{sa.value}}</span>
            <i class="fa fa-times"></i>
        </button>
    </div>
    <div class="d-flex flex-wrap">
        <div class="itl-sidebar">
            <div class="itl-sidebar-option">
                <h4>Search Filter</h4>
                <a class="itl-toggle-bars" href="">
                    <span class="open">Expand All</span>
                    <span class="itlclose">Collapse All</span>
                </a>
            </div>
            <!-- sidebar start -->
            <button class="accordion">Search
                <i class="fa fa-plus"></i>
                <i class="fa fa-minus"></i>
            </button>
            <div class="panel">
                <input type="text" class="st-1" @keyup="getSearchData" v-model="searchQuery" id="itlSearch"
                    name="itlSearch">
                <div class="item" v-if="cLoader">
                    <i class="loader --2"></i>
                </div>
                <ul id="showSearchResponse" v-if="searchResponse.length">
                    <li v-for="(s,snd) in searchResponse" :key="snd">
                        <a :href="s.url">
                            <div class="itl-q-main">
                                <div class="image">
                                    <img :src="s.image" alt="s.title">
                                </div>
                                <div class="itl-q-content">
                                    <h5>{{s.title}}</h5>
                                    <h6 v-if="s.year.length">{{s.year[0]}}</h6>
                                </div>
                            </div>
                        </a>
                    </li>
                    <li v-if="" class="footer-list-item">
                        <a :href="'https://videoconsolidator.tv/search-result/?sq=' + searchQuery">See All Result For:
                            "{{searchQuery}}"</a>
                    </li>
                </ul>
            </div>

            <button class="accordion">Title
                <i class="fa fa-plus"></i>
                <i class="fa fa-minus"></i>
            </button>
            <div class="panel">
                <input type="text" class="st-1" @change="changeTitle(0)" v-model="selectedAllData[0].value"
                    id="itlTitle" name="itlTitle">
            </div>

            <button class="accordion">Keywords
                <i class="fa fa-plus"></i>
                <i class="fa fa-minus"></i>
            </button>
            <div class="panel">
                <input type="text" class="st-1" @change="changeTitle(11)" v-model="selectedAllData[11].value"
                    id="itlSpcontent" name="itlSpcontent">
            </div>


            <button class="accordion">Title Type
                <i class="fa fa-plus"></i>
                <i class="fa fa-minus"></i>
            </button>
            <div class="panel itl-ttype">
                <div class="select-btns">

                    <button v-for="(t, tnd) in ttype_terms"
                        :class="{ active: selectedAllData[14].value != null && selectedAllData[14].value.split(',').includes(t.slug) }"
                        :key="tnd" @click="selectChips($event, t.slug,14)" class="itl-btn round">
                        {{ t.name }}
                    </button>


                </div>
            </div>


            <button class="accordion">Comments
                <i class="fa fa-plus"></i>
                <i class="fa fa-minus"></i>
            </button>
            <div class="panel">
                <input type="text" class="st-1" @keyup="getComments(12)" v-model="selectedAllData[12].value"
                    id="itlComment" name="itlComment">

                <div class="item" v-if="cLoader">
                    <i class="loader --2"></i>
                </div>
                <ul id="showCommentsResponse" v-if="commentsRes.length">
                    <li v-for="(cm,cmnd) in commentsRes" :key="cmnd" @click="getTheComment(cm.comment_content)">
                        <!-- <h6 >{{s.year[0]}}</h6> -->
                        {{cm.comment_content}}
                    </li>
                </ul>
            </div>

            <!-- <button class="accordion">Studio
                <i class="fa fa-plus"></i>
                <i class="fa fa-minus"></i>
            </button>
            <div class="panel">
                <input type="text" class="st-1" @change="changeStudio(1)" v-model="selectedAllData[1].value"
                    id="itlStudio" name="itlStudio">
            </div> -->

            <button class="accordion">Genre
                <i class="fa fa-plus"></i>
                <i class="fa fa-minus"></i>
            </button>
            <div class="panel itl-genre">
                <div class="select-btns">

                    <button v-for="(g, gnd) in genres_terms"
                        :class="{ active: selectedAllData[2].value != null && selectedAllData[2].value.split(',').includes(g.slug) }"
                        :key="gnd" @click="selectChips($event, g.slug,2)" class="itl-btn round">
                        {{ g.name }}
                    </button>


                </div>
            </div>

            <button class="accordion">Companies
                <i class="fa fa-plus"></i>
                <i class="fa fa-minus"></i>
            </button>
            <div class="panel">
                <div class="select-btns">

                    <button v-for="(cc, ccnd) in companies_terms"
                        :class="{ active: selectedAllData[15].value != null && selectedAllData[15].value.split(',').includes(cc.slug) }"
                        :key="ccnd" @click="selectChips($event, cc.slug,15)" class="itl-btn round">
                        {{ cc.name }}
                    </button>


                </div>
            </div>

            <button class="accordion">US Certificates
                <i class="fa fa-plus"></i>
                <i class="fa fa-minus"></i>
            </button>
            <div class="panel">
                <div class="select-btns">

                    <button v-for="(ct, ctnd) in certificate_terms"
                        :class="{ active: selectedAllData[16].value != null && selectedAllData[16].value.split(',').includes(ct.slug) }"
                        :key="ctnd" @click="selectChips($event, ct.slug,16)" class="itl-btn round">
                        {{ ct.name }}
                    </button>


                </div>
            </div>


            <button class="accordion">Languages
                <i class="fa fa-plus"></i>
                <i class="fa fa-minus"></i>
            </button>
            <div class="panel">
                <input class="st-1 inside" type="text" v-model="queryLanguage" @focus="handleFLanguage"
                    @keyup="getLanguageData">
                <div class="item" v-if="cLoader">
                    <i class="loader --2"></i>
                </div>
                <div id="langList" class="show-selected-cast inside" v-if="fLangStatus">
                    <div class="select-lang inside">
                        <div v-for="(ln,lnd) in filteredLang" :key="lnd" class="lang-item inside">
                            <input class="lang_check inside" type="checkbox" :id="ln.slug" :value="ln"
                                @change="changeLang(ln,'lang')" :checked="checkedNames.some(c => c.slug === ln.slug)">
                            <label :for="ln.slug" class="inside">{{ln.name}}</label>
                        </div>
                    </div>
                </div>
                <div id="langList2" class="show-selected-cast inside" v-if="checkedNames.length">
                    <div class="select-btns inside">
                        <button v-for="(ca,cind) in checkedNames" :key="cind" @click="removeLang(ca.slug,'lang')"
                            class="itl-btn round w-close inside">
                            {{ca.name}}
                            <i class="fa fa-times inside"></i>
                        </button>
                    </div>
                </div>
            </div>

            <button class="accordion">Streaming Platform
                <i class="fa fa-plus"></i>
                <i class="fa fa-minus"></i>
            </button>
            <div class="panel">
                <input class="st-1 inside" type="text" v-model="querySP" @focus="handleSP" @keyup="getSPData">
                <div class="item" v-if="cLoader">
                    <i class="loader --2"></i>
                </div>
                <div id="SPList" class="show-selected-cast inside" v-if="fSPStatus">
                    <div class="select-lang inside">
                        <div v-for="(ln,lnd) in filteredSP" :key="lnd" class="lang-item inside">
                            <input class="lang_check inside" type="checkbox" :id="ln.slug" :value="ln"
                                @change="changeLang(ln,'sp')" :checked="checkedSP.some(c => c.slug === ln.slug)">
                            <label :for="ln.slug" class="inside">{{ln.name}}</label>
                        </div>
                    </div>
                </div>
                <div id="SPList2" class="show-selected-cast inside" v-if="checkedSP.length">
                    <div class="select-btns inside">
                        <button v-for="(ca,cind) in checkedSP" :key="cind" @click="removeLang(ca.slug,'sp')"
                            class="itl-btn round w-close inside">
                            {{ca.name}}
                            <i class="fa fa-times inside"></i>
                        </button>
                    </div>
                </div>
            </div>


            <button class="accordion">Tag
                <i class="fa fa-plus"></i>
                <i class="fa fa-minus"></i>
            </button>
            <div class="panel">
                <div class="select-btns">
                    <button v-for="(t,tnd) in tag_terms" :key="tnd"
                        :class="{ active: selectedAllData[3].value != null && selectedAllData[3].value.split(',').includes(t.slug) }"
                        @click="selectChips($event, t.slug,3)" class="itl-btn round">
                        {{t.name}}
                    </button>

                </div>
            </div>


            <button class="accordion">Awards & Recognition
                <i class="fa fa-plus"></i>
                <i class="fa fa-minus"></i>
            </button>
            <div class="panel">
                <div class="select-btns">
                    <button v-for="(aw,awnd) in awards_terms" :key="awnd" @click="selectChips($event, aw.slug,9)"
                        :class="{ active: selectedAllData[9].value != null && selectedAllData[9].value.split(',').includes(aw.slug) }"
                        class="itl-btn round">
                        {{aw.name}}
                    </button>

                </div>
            </div>

            <button class="accordion">Cast
                <i class="fa fa-plus"></i>
                <i class="fa fa-minus"></i>
            </button>
            <div class="panel">
                <input class="st-1" type="text" v-model="queryCastString" @keyup="getCastData">
                <div class="item" v-if="cLoader">
                    <i class="loader --2"></i>
                </div>
                <div class="show-selected-cast" v-if="choosenCast.length">
                    <div class="select-btns">
                        <button v-for="(ca,cind) in choosenCast" :key="cind" @click="selectCast(ca.slug,'rem')"
                            class="itl-btn round w-close">
                            {{ca.name}}
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
                <ul id="showCastResponse" v-if="castResArr.length">
                    <li v-for="(cast,ind) in castResArr" :key="ind" @click="selectCast(cast)">
                        <div class="cast-box">
                            <img v-if="cast.image" :src="cast.image" class="cast-image" alt="">
                            <img v-if="!cast.image" src="https://placehold.jp/60x60.png" class="cast-image" alt="">
                            <div class="cast-content">
                                <p>{{ cast.name }}</p>
                                <p>{{cast.des}}</p>
                            </div>
                        </div>


                    </li>
                </ul>
            </div>

            <button class="accordion">Year
                <i class="fa fa-plus"></i>
                <i class="fa fa-minus"></i>
            </button>
            <div class="panel">
                <input type="text" class="st-1" @change="changeYear($event)" v-model="selectedAllData[5].value"
                    id="itlYear" name="itlYear">
            </div>

            <button class="accordion">Director
                <i class="fa fa-plus"></i>
                <i class="fa fa-minus"></i>
            </button>
            <div class="panel">
                <input type="text" class="st-1" @change="changeDirector(6)" v-model="selectedAllData[6].value"
                    id="itlDirector" name="itlDirector">
            </div>

            <!-- 
            <button class="accordion">Network
                <i class="fa fa-plus"></i>
                <i class="fa fa-minus"></i>
            </button>
            <div class="panel">
                <input type="text" class="st-1" @change="changeNetwork(7)" v-model="selectedAllData[7].value"
                    id="itlNetwork" name="itlNetwork">
            </div> -->

            <button class="accordion">Rating
                <i class="fa fa-plus"></i>
                <i class="fa fa-minus"></i>
            </button>
            <div class="panel">
                <div class="d-flex">
                    <input type="text" class="st-1 st-2" @change="changeRating($event,'min')" v-model="minRating"
                        id="itlRatingMin" name="itlRatingMin" placeholder="e.g. 1.0">
                    <h6 style="line-height: 3;">To</h6>
                    <input type="text" class="st-1 st-2" @change="changeRating($event,'max')" v-model="maxRating"
                        id="itlRatingMax" name="itlRatingMax" placeholder="e.g. 10.0">
                </div>

            </div>

            <button class="accordion">Number of Votes
                <i class="fa fa-plus"></i>
                <i class="fa fa-minus"></i>
            </button>
            <div class="panel">
                <div class="d-flex">
                    <input type="text" class="st-1 st-2" @change="changeVotes($event,'min')" v-model="minVotes"
                        id="itlVotesMin" name="itlVotesMin" placeholder="e.g. 0">
                    <h6 style="line-height: 3;">To</h6>
                    <input type="text" class="st-1 st-2" @change="changeVotes($event,'max')" v-model="maxVotes"
                        id="itlVotesMax" name="itlVotesMax" placeholder="e.g. 700000">
                </div>

            </div>

            <button class="accordion">Runtime
                <i class="fa fa-plus"></i>
                <i class="fa fa-minus"></i>
            </button>
            <div class="panel">
                <p>In minutes</p>
                <div class="d-flex">
                    <input type="text" class="st-1 st-2" @change="changeRTime($event,'min')" v-model="minRT"
                        id="itlRMin" name="itlRMin" placeholder="e.g. 1">
                    <h6 style="line-height: 3;">To</h6>
                    <input type="text" class="st-1 st-2" @change="changeRTime($event,'max')" v-model="maxRT"
                        id="itlRMax" name="itlRMax" placeholder="e.g. 180">
                </div>

            </div>


            <!-- sidebar end -->
        </div>
        <div class="itl-grid">

            <div class="itl-toolbar">
                <div class="count-status" v-if="postsData.length">
                    <div class="" v-if="postsData.length < 10">
                        <span>1</span>-<span>{{postsData.length}}</span>
                    </div>
                    <div class="" v-if="postsData.length >= 10">
                        <span>1</span>-<span>{{postsData.length}}</span> of <span>{{fposts}}</span>
                    </div>

                </div>
                <div class="itl-sort">
                    <span class="ipc-simple-select__container"><label class="ipc-simple-select__front-label"
                            for="adv-srch-sort-by">Sort by</label>
                        <span class="ipc-simple-select ipc-simple-select--base ipc-simple-select--on-accent2">
                            <select :disabled="postsData.length == 1" id="adv-srch-sort-by" aria-label="Sort by"
                                @change="changeSort()" v-model="sortSelected" class="ipc-simple-select__input">
                                <option value=""></option>
                                <option value="popularity">Popularity</option>
                                <option value="title">A-Z</option>
                                <option value="rating">Rating</option>
                                <option value="release_date">Release Date</option>


                            </select></span></span>
                    <button data-testid="test-sort-order" id="adv-srch-sort-order" class="itl-btn-st-1"
                        :class="{active: sortOrder=='ASC'}" @click="changeSortType($event)" title="Sort order"
                        role="button" tabindex="0" aria-label="Sort order" :disabled="postsData.length == 1">
                        <svg width="24" height="24" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                            fill="currentColor" role="presentation">
                            <path fill="none" d="M0 0h24v24H0V0z"></path>
                            <path
                                d="M16 17.01V11c0-.55-.45-1-1-1s-1 .45-1 1v6.01h-1.79c-.45 0-.67.54-.35.85l2.79 2.78c.2.19.51.19.71 0l2.79-2.78c.32-.31.09-.85-.35-.85H16zM8.65 3.35L5.86 6.14c-.32.31-.1.85.35.85H8V13c0 .55.45 1 1 1s1-.45 1-1V6.99h1.79c.45 0 .67-.54.35-.85L9.35 3.35a.501.501 0 0 0-.7 0z">
                            </path>
                        </svg>
                    </button>
                </div>
                <div class="itl-layout">

                    <button id="view_detial" @click="changeLayout('view_detial')"
                        class="itl-btn-st-1 active ipc-icon-button ipc-icon-button--base ipc-icon-button--onAccent2"
                        title="Selected:  Detailed view" role="button" tabindex="0"
                        aria-label="Selected:  Detailed view" aria-disabled="false">
                        <svg width="24" height="24" xmlns="http://www.w3.org/2000/svg"
                            class="ipc-icon ipc-icon--list-inline" viewBox="0 0 24 24" fill="currentColor"
                            role="presentation">
                            <path
                                d="M1.5 13.5c.825 0 1.5-.675 1.5-1.5s-.675-1.5-1.5-1.5S0 11.175 0 12s.675 1.5 1.5 1.5zm0 5c.825 0 1.5-.675 1.5-1.5s-.675-1.5-1.5-1.5S0 16.175 0 17s.675 1.5 1.5 1.5zm0-10C2.325 8.5 3 7.825 3 7s-.675-1.5-1.5-1.5S0 6.175 0 7s.675 1.5 1.5 1.5zm4.857 5h16.286c.746 0 1.357-.675 1.357-1.5s-.61-1.5-1.357-1.5H6.357C5.611 10.5 5 11.175 5 12s.61 1.5 1.357 1.5zm0 5h16.286c.746 0 1.357-.675 1.357-1.5s-.61-1.5-1.357-1.5H6.357C5.611 15.5 5 16.175 5 17s.61 1.5 1.357 1.5zM5 7c0 .825.61 1.5 1.357 1.5h16.286C23.389 8.5 24 7.825 24 7s-.61-1.5-1.357-1.5H6.357C5.611 5.5 5 6.175 5 7zm-3.5 6.5c.825 0 1.5-.675 1.5-1.5s-.675-1.5-1.5-1.5S0 11.175 0 12s.675 1.5 1.5 1.5zm0 5c.825 0 1.5-.675 1.5-1.5s-.675-1.5-1.5-1.5S0 16.175 0 17s.675 1.5 1.5 1.5zm0-10C2.325 8.5 3 7.825 3 7s-.675-1.5-1.5-1.5S0 6.175 0 7s.675 1.5 1.5 1.5zm4.857 5h16.286c.746 0 1.357-.675 1.357-1.5s-.61-1.5-1.357-1.5H6.357C5.611 10.5 5 11.175 5 12s.61 1.5 1.357 1.5zm0 5h16.286c.746 0 1.357-.675 1.357-1.5s-.61-1.5-1.357-1.5H6.357C5.611 15.5 5 16.175 5 17s.61 1.5 1.357 1.5zM5 7c0 .825.61 1.5 1.357 1.5h16.286C23.389 8.5 24 7.825 24 7s-.61-1.5-1.357-1.5H6.357C5.611 5.5 5 6.175 5 7z">
                            </path>
                        </svg>
                    </button>
                    <button id="view_grid" @click="changeLayout('view_grid')"
                        class="itl-btn-st-1 ipc-icon-button ipc-icon-button--base ipc-icon-button--onBase"
                        title=" Grid view" role="button" tabindex="0" aria-label=" Grid view" aria-disabled="false"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                            class="ipc-icon ipc-icon--grid-view" viewBox="0 0 24 24" fill="currentColor"
                            role="presentation">
                            <path
                                d="M4.8 14h2.4c.44 0 .8-.3.8-.667v-2.666C8 10.3 7.64 10 7.2 10H4.8c-.44 0-.8.3-.8.667v2.666c0 .367.36.667.8.667zm0-6h2.4c.44 0 .8-.3.8-.667V4.667C8 4.3 7.64 4 7.2 4H4.8c-.44 0-.8.3-.8.667v2.666C4 7.7 4.36 8 4.8 8zm0 12h2.4c.44 0 .8-.3.8-.667v-2.666C8 16.3 7.64 16 7.2 16H4.8c-.44 0-.8.3-.8.667v2.666c0 .367.36.667.8.667zm6 0h2.4c.44 0 .8-.3.8-.667v-2.666c0-.367-.36-.667-.8-.667h-2.4c-.44 0-.8.3-.8.667v2.666c0 .367.36.667.8.667zm6 0h2.4c.44 0 .8-.3.8-.667v-2.666c0-.367-.36-.667-.8-.667h-2.4c-.44 0-.8.3-.8.667v2.666c0 .367.36.667.8.667zm-6-6h2.4c.44 0 .8-.3.8-.667v-2.666c0-.367-.36-.667-.8-.667h-2.4c-.44 0-.8.3-.8.667v2.666c0 .367.36.667.8.667zm0-6h2.4c.44 0 .8-.3.8-.667V4.667C14 4.3 13.64 4 13.2 4h-2.4c-.44 0-.8.3-.8.667v2.666c0 .367.36.667.8.667zm5.2 2.667v2.666c0 .367.36.667.8.667h2.4c.44 0 .8-.3.8-.667v-2.666c0-.367-.36-.667-.8-.667h-2.4c-.44 0-.8.3-.8.667zm0-6v2.666c0 .367.36.667.8.667h2.4c.44 0 .8-.3.8-.667V4.667C20 4.3 19.64 4 19.2 4h-2.4c-.44 0-.8.3-.8.667z">
                            </path>
                        </svg>
                    </button>
                    <button id="view_compact" @click="changeLayout('view_compact')"
                        class="itl-btn-st-1 ipc-icon-button ipc-icon-button--base ipc-icon-button--onBase"
                        title=" Compact view" role="button" tabindex="0" aria-label=" Compact view"
                        aria-disabled="false"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                            class="ipc-icon ipc-icon--menu" viewBox="0 0 24 24" fill="currentColor" role="presentation">
                            <path fill="none" d="M0 0h24v24H0V0z"></path>
                            <path
                                d="M4 18h16c.55 0 1-.45 1-1s-.45-1-1-1H4c-.55 0-1 .45-1 1s.45 1 1 1zm0-5h16c.55 0 1-.45 1-1s-.45-1-1-1H4c-.55 0-1 .45-1 1s.45 1 1 1zM3 7c0 .55.45 1 1 1h16c.55 0 1-.45 1-1s-.45-1-1-1H4c-.55 0-1 .45-1 1z">
                            </path>
                        </svg>
                    </button>

                </div>
            </div>
            <!-- Empty grid image Start   -->

            <div class="itl-layout-box">

                <!-- Detial View -->
                <ul v-if="view_detial" class="itl-detail-view">
                    <li v-for="(post,ind) in postsData" :key="ind" class="d-item youId"
                        :data-trailer-id="post.trailer_id[0]">
                        <div class="itl-poster">
                            <a :href="post.url" class="">
                                <img :src="post.image" alt="poster" class="">
                            </a>
                        </div>
                        <div class="itl-poster-content youPlay">
                            <a :href="post.url" class="">
                                <h5 class="itl-title"><span>{{ind + 1}}.</span>{{post.title}}</h5>
                            </a>
                            <h6 class="">{{post.year[0]}}</h6>

                            <p v-if="post.content" class="itl-poster-desc">
                                {{post.content}}
                            </p>
                            <div class="review">
                                <h6 v-if="post.rating" class="">Rating: {{post.rating}}</h6>
                                <h6 v-if="post.vote_count" class="">Votes <span class="">{{post.vote_count}}</span></h6>
                            </div>

                        </div>
                    </li>
                </ul>

                <!-- Grid View -->
                <div v-if="view_grid" class="itl-grid-view">
                    <div v-for="(post,ind) in postsData" :key="ind" class="itl-grid-item youId"
                        :data-trailer-id="post.trailer_id[0]">
                        <div class="itl-grid-poster youPlay">
                            <a :href="post.url" class="">
                                <img :src="post.image" alt="poster" class="">
                            </a>
                        </div>
                        <div class="itl-grid-content">

                            <a :href="post.url" class="">
                                <h5 class="itl-title"><span>{{ind + 1}}.</span>{{post.title}}</h5>
                            </a>
                            <h6 class="">{{post.year[0]}}</h6>



                            <div class="itl-grid-footer">
                                <div class="review">
                                    <h6 class="">Rating: {{post.rating}}</h6>
                                    <h6 class="">Votes <span class="">{{post.vote_count}}</span></h6>
                                </div>
                                <a :href="post.url" class="">
                                    <button class="itl-btn btn-st-1 btn-detail">Details</button>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Compact View -->
                <ul v-if="view_compact" class="itl-compact-view">
                    <li v-for="(post,ind) in postsData" :key="ind" class="c-item youId"
                        :data-trailer-id="post.trailer_id[0]">
                        <div class="itl-poster">
                            <a :href="post.url" class="">
                                <img :src="post.image" alt="" class="">
                            </a>
                        </div>
                        <div class="itl-poster-content youPlay">
                            <a :href="post.url" class="">
                                <h5 class="itl-title"><span>{{ind + 1}}.</span> {{post.title}}</h5>
                            </a>
                            <h6 class="">{{post.year[0]}}</h6>
                            <div class="review">
                                <h6 class="">Rating: {{post.rating}}</h6>
                                <h6 class="">Votes <span class="">{{post.vote_count}}</span></h6>
                            </div>
                        </div>
                    </li>
                </ul>


                <div class="load-more" v-if="page < maxPage">
                    <div v-if="itlLoader" class="lds-ellipsis">
                        <div></div>
                        <div></div>
                        <div></div>
                        <div></div>
                    </div>
                    <button v-if="!itlLoader" id="itlLoadMore" @click="loadMoreContent()" class="itl-btn">Load
                        More</button>
                </div>

            </div>



            <!-- Empty grid image End -->
        </div>
    </div>
</div>
<?php
        return ob_get_clean();
    }

    function itl_get_search_movies()
    {

        $searchQuery = isset($_POST['sq']) ? $_POST['sq'] : null;
        $page = isset($_POST['page']) ? $_POST['page'] : null;
        $sort = isset($_POST['ss']) ? $_POST['ss'] : null;
        $sort_order = isset($_POST['so']) ? $_POST['so'] : null;

        global $wpdb;
        $args = array(
            'post_status' => 'publish',
            'post_type' => array('tvshows', 'movies'),
            'posts_per_page' => 10,
            'paged' => $page,
        );
        if ($searchQuery != null) {
            $args += array('s' => $searchQuery,);
            $args['search_title'] = true;
        }

        // Sort Title
        if ($sort != null and $sort == 'title') {
            $args['orderby'] = 'title';
            $args['order'] = $sort_order;
        }
        // Sort Rating
        if ($sort != null and $sort == 'rating') {
            $args['orderby'] = 'meta_value_num';
            $args['order'] = $sort_order;
            $args['meta_key'] = 'imdbRating';
        }
        // Sort Popularity
        if ($sort != null and $sort == 'popularity') {
            $args['orderby'] = 'meta_value_num';
            $args['order'] = $sort_order;
            $args['meta_key'] = 'dt_views_count';
        }
        // Sort release_date
        if ($sort != null and $sort == 'release_date') {
            $args['orderby'] = 'meta_value';
            $args['order'] = $sort_order;
            $args['meta_key'] = 'release_date';
        }

        $loop = new WP_Query($args);
        while ($loop->have_posts()) {
            $loop->the_post();
            $post_id = get_the_ID();

            $imdbRating = get_post_meta($post_id, 'imdbRating', true);
            $viewsCount = get_post_meta($post_id, 'dt_views_count', true);
            $runtime = get_post_meta($post_id, 'runtime', true);
            $releaseDate = get_post_meta($post_id, 'release_date', true);
            $voteCount = get_post_meta($post_id, 'vote_count', true);
            $trailer_id = get_post_meta($post_id, "youtube_id");
            $trailer_id = str_replace(array('[', ']'), '', $trailer_id);
            $content = get_post_field('post_content', $post_id, true);
            $content = wp_strip_all_tags($content);
            // Remove HTML tags and limit to 50 words
            $content = html_entity_decode(wp_trim_words($content, 45));




            // Genres
            $terms_genres = get_the_terms($post_id, 'genres');

            // Check if there are any terms
            $post_genres = [];
            if ($terms_genres && !is_wp_error($terms_genres)) {
                foreach ($terms_genres as $term) {
                    $term_name = $term->name;
                    $term_slug = $term->slug;
                    array_push($post_genres, $term_name);
                }
            }

            // Director
            $terms_directors = get_the_terms($post_id, 'dtdirector');

            // Check if there are any terms
            $post_directors = [];
            if ($terms_directors && !is_wp_error($terms_directors)) {
                foreach ($terms_directors as $term) {
                    $term_name = $term->name;
                    $term_slug = $term->slug;
                    array_push($post_directors, $term_name);
                }
            }

            // Studio
            $terms_studio = get_the_terms($post_id, 'dtstudio');

            // Check if there are any terms
            $post_studio = [];
            if ($terms_studio && !is_wp_error($terms_studio)) {
                foreach ($terms_studio as $term) {
                    $term_name = $term->name;
                    $term_slug = $term->slug;
                    array_push($post_studio, $term_name);
                }
            }

            // Networks
            $terms_networks = get_the_terms($post_id, 'dtnetworks');

            // Check if there are any terms
            $post_networks = [];
            if ($terms_networks && !is_wp_error($terms_networks)) {
                foreach ($terms_networks as $term) {
                    $term_name = $term->name;
                    $term_slug = $term->slug;
                    array_push($post_networks, $term_name);
                }
            }

            // Years
            $terms_years = get_the_terms($post_id, 'dtyear');

            // Check if there are any terms
            $post_years = [];
            if ($terms_years && !is_wp_error($terms_years)) {
                foreach ($terms_years as $term) {
                    $term_name = $term->name;
                    $term_slug = $term->slug;
                    array_push($post_years, $term_name);
                }
            }

            // Tags
            $terms_tags = get_the_terms($post_id, 'post_tag');

            // Check if there are any terms
            $post_tags = [];
            if ($terms_tags && !is_wp_error($terms_tags)) {
                foreach ($terms_tags as $term) {
                    $term_name = $term->name;
                    $term_slug = $term->slug;
                    array_push($post_tags, $term_name);
                }
            }


            // Casts
            $terms_casts = get_the_terms($post_id, 'dtcast');


            $post_casts = [];
            if ($terms_casts && !is_wp_error($terms_casts)) {
                foreach ($terms_casts as $term) {
                    $term_name = $term->name;
                    $term_slug = $term->slug;
                    array_push($post_casts, $term_name);
                }
            }

            // Get the post type
            $p_type = get_post_type();

            $associative = [
                'id' => $post_id,
                'url' => get_permalink($post_id),
                'image' => get_the_post_thumbnail_url($post_id),
                'title' => html_entity_decode(get_the_title()),
                'genres' => $post_genres,
                'directors' => $post_directors,
                'year' => $post_years,
                'tags' => $post_tags,
                'rating' => $imdbRating,
                'cast' => $post_casts,
                'post_type' => $p_type,
                'studio' => $post_studio,
                'networks' => $post_networks,
                'vote_count' => $voteCount,
                'trailer_id' => $trailer_id,
            ];

            $post_data[] = $associative;
        }
        wp_reset_postdata();

        // Return JSON response
        echo json_encode(
            array(
                'postData' => $post_data,
                'maxPage' => $loop->max_num_pages,
                'fposts' => $loop->found_posts,
                '$args' => $args,
            )
        );

        wp_die();
    }

    function itl_search_results_shortcode($atts)
    {

        // $this->enqueue_files();
        wp_enqueue_style('bootstrap-cpt');
        wp_enqueue_style('itl-filter-css');

        wp_enqueue_script('popper');
        wp_enqueue_script('bootstrap-js');
        wp_enqueue_script('vue-js');
        wp_enqueue_script('itl-search-js');

        $localize = array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            // 'nonce' => wp_create_nonce('itl_movies_filter_nonce')
        );
        wp_localize_script('itl-search-js', 'itlObj', $localize);


        ob_start();
    ?>
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
<!-- <div id="itlItemFilter"> -->

<div id="itlSearchResult" class="itl-search-result">
    <div class="itl-grid">

        <div class="itl-toolbar">
            <div class="count-status" v-if="postsData.length">
                <div class="" v-if="postsData.length < 10">
                    <span>1</span>-<span>{{postsData.length}}</span>
                </div>
                <div class="" v-if="postsData.length >= 10">
                    <span>1</span>-<span>{{postsData.length}}</span> of <span>{{fposts}}</span>
                </div>

            </div>
            <div class="right">
                <div class="itl-sort">
                    <span class="ipc-simple-select__container"><label class="ipc-simple-select__front-label"
                            for="adv-srch-sort-by">Sort by</label>
                        <span class="ipc-simple-select ipc-simple-select--base ipc-simple-select--on-accent2">
                            <select :disabled="postsData.length == 1" id="adv-srch-sort-by" aria-label="Sort by"
                                @change="changeSort()" v-model="sortSelected" class="ipc-simple-select__input">
                                <option value=""></option>
                                <option value="popularity">Popularity</option>
                                <option value="title">A-Z</option>
                                <option value="rating">Rating</option>
                                <option value="release_date">Release Date</option>


                            </select></span></span>
                    <button :disabled="sortSelected == '' && sortSelected == null" data-testid="test-sort-order"
                        id="adv-srch-sort-order" class="itl-btn-st-1" :class="{active: sortOrder=='ASC'}"
                        @click="changeSortType($event)" title="Sort order" role="button" tabindex="0"
                        aria-label="Sort order" :disabled="postsData.length == 1">
                        <svg width="24" height="24" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                            fill="currentColor" role="presentation">
                            <path fill="none" d="M0 0h24v24H0V0z"></path>
                            <path
                                d="M16 17.01V11c0-.55-.45-1-1-1s-1 .45-1 1v6.01h-1.79c-.45 0-.67.54-.35.85l2.79 2.78c.2.19.51.19.71 0l2.79-2.78c.32-.31.09-.85-.35-.85H16zM8.65 3.35L5.86 6.14c-.32.31-.1.85.35.85H8V13c0 .55.45 1 1 1s1-.45 1-1V6.99h1.79c.45 0 .67-.54.35-.85L9.35 3.35a.501.501 0 0 0-.7 0z">
                            </path>
                        </svg>
                    </button>
                </div>
                <div class="itl-layout">

                    <button id="view_detial" @click="changeLayout('view_detial')"
                        class="itl-btn-st-1 active ipc-icon-button ipc-icon-button--base ipc-icon-button--onAccent2"
                        title="Selected:  Detailed view" role="button" tabindex="0"
                        aria-label="Selected:  Detailed view" aria-disabled="false">
                        <svg width="24" height="24" xmlns="http://www.w3.org/2000/svg"
                            class="ipc-icon ipc-icon--list-inline" viewBox="0 0 24 24" fill="currentColor"
                            role="presentation">
                            <path
                                d="M1.5 13.5c.825 0 1.5-.675 1.5-1.5s-.675-1.5-1.5-1.5S0 11.175 0 12s.675 1.5 1.5 1.5zm0 5c.825 0 1.5-.675 1.5-1.5s-.675-1.5-1.5-1.5S0 16.175 0 17s.675 1.5 1.5 1.5zm0-10C2.325 8.5 3 7.825 3 7s-.675-1.5-1.5-1.5S0 6.175 0 7s.675 1.5 1.5 1.5zm4.857 5h16.286c.746 0 1.357-.675 1.357-1.5s-.61-1.5-1.357-1.5H6.357C5.611 10.5 5 11.175 5 12s.61 1.5 1.357 1.5zm0 5h16.286c.746 0 1.357-.675 1.357-1.5s-.61-1.5-1.357-1.5H6.357C5.611 15.5 5 16.175 5 17s.61 1.5 1.357 1.5zM5 7c0 .825.61 1.5 1.357 1.5h16.286C23.389 8.5 24 7.825 24 7s-.61-1.5-1.357-1.5H6.357C5.611 5.5 5 6.175 5 7zm-3.5 6.5c.825 0 1.5-.675 1.5-1.5s-.675-1.5-1.5-1.5S0 11.175 0 12s.675 1.5 1.5 1.5zm0 5c.825 0 1.5-.675 1.5-1.5s-.675-1.5-1.5-1.5S0 16.175 0 17s.675 1.5 1.5 1.5zm0-10C2.325 8.5 3 7.825 3 7s-.675-1.5-1.5-1.5S0 6.175 0 7s.675 1.5 1.5 1.5zm4.857 5h16.286c.746 0 1.357-.675 1.357-1.5s-.61-1.5-1.357-1.5H6.357C5.611 10.5 5 11.175 5 12s.61 1.5 1.357 1.5zm0 5h16.286c.746 0 1.357-.675 1.357-1.5s-.61-1.5-1.357-1.5H6.357C5.611 15.5 5 16.175 5 17s.61 1.5 1.357 1.5zM5 7c0 .825.61 1.5 1.357 1.5h16.286C23.389 8.5 24 7.825 24 7s-.61-1.5-1.357-1.5H6.357C5.611 5.5 5 6.175 5 7z">
                            </path>
                        </svg>
                    </button>
                    <button id="view_grid" @click="changeLayout('view_grid')"
                        class="itl-btn-st-1 ipc-icon-button ipc-icon-button--base ipc-icon-button--onBase"
                        title=" Grid view" role="button" tabindex="0" aria-label=" Grid view" aria-disabled="false"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                            class="ipc-icon ipc-icon--grid-view" viewBox="0 0 24 24" fill="currentColor"
                            role="presentation">
                            <path
                                d="M4.8 14h2.4c.44 0 .8-.3.8-.667v-2.666C8 10.3 7.64 10 7.2 10H4.8c-.44 0-.8.3-.8.667v2.666c0 .367.36.667.8.667zm0-6h2.4c.44 0 .8-.3.8-.667V4.667C8 4.3 7.64 4 7.2 4H4.8c-.44 0-.8.3-.8.667v2.666C4 7.7 4.36 8 4.8 8zm0 12h2.4c.44 0 .8-.3.8-.667v-2.666C8 16.3 7.64 16 7.2 16H4.8c-.44 0-.8.3-.8.667v2.666c0 .367.36.667.8.667zm6 0h2.4c.44 0 .8-.3.8-.667v-2.666c0-.367-.36-.667-.8-.667h-2.4c-.44 0-.8.3-.8.667v2.666c0 .367.36.667.8.667zm6 0h2.4c.44 0 .8-.3.8-.667v-2.666c0-.367-.36-.667-.8-.667h-2.4c-.44 0-.8.3-.8.667v2.666c0 .367.36.667.8.667zm-6-6h2.4c.44 0 .8-.3.8-.667v-2.666c0-.367-.36-.667-.8-.667h-2.4c-.44 0-.8.3-.8.667v2.666c0 .367.36.667.8.667zm0-6h2.4c.44 0 .8-.3.8-.667V4.667C14 4.3 13.64 4 13.2 4h-2.4c-.44 0-.8.3-.8.667v2.666c0 .367.36.667.8.667zm5.2 2.667v2.666c0 .367.36.667.8.667h2.4c.44 0 .8-.3.8-.667v-2.666c0-.367-.36-.667-.8-.667h-2.4c-.44 0-.8.3-.8.667zm0-6v2.666c0 .367.36.667.8.667h2.4c.44 0 .8-.3.8-.667V4.667C20 4.3 19.64 4 19.2 4h-2.4c-.44 0-.8.3-.8.667z">
                            </path>
                        </svg>
                    </button>
                    <button id="view_compact" @click="changeLayout('view_compact')"
                        class="itl-btn-st-1 ipc-icon-button ipc-icon-button--base ipc-icon-button--onBase"
                        title=" Compact view" role="button" tabindex="0" aria-label=" Compact view"
                        aria-disabled="false"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                            class="ipc-icon ipc-icon--menu" viewBox="0 0 24 24" fill="currentColor" role="presentation">
                            <path fill="none" d="M0 0h24v24H0V0z"></path>
                            <path
                                d="M4 18h16c.55 0 1-.45 1-1s-.45-1-1-1H4c-.55 0-1 .45-1 1s.45 1 1 1zm0-5h16c.55 0 1-.45 1-1s-.45-1-1-1H4c-.55 0-1 .45-1 1s.45 1 1 1zM3 7c0 .55.45 1 1 1h16c.55 0 1-.45 1-1s-.45-1-1-1H4c-.55 0-1 .45-1 1z">
                            </path>
                        </svg>
                    </button>

                </div>
            </div>

        </div>
        <!-- Empty grid image Start   -->

        <div class="itl-layout-box">

            <!-- Detial View -->
            <ul v-if="view_detial" class="itl-detail-view">
                <li v-for="(post,ind) in postsData" :key="ind" class="d-item youId"
                    :data-trailer-id="post.trailer_id[0]">
                    <div class="itl-poster">
                        <a :href="post.url" class="">
                            <img :src="post.image" alt="poster" class="">
                        </a>
                    </div>
                    <div class="itl-poster-content youPlay">
                        <a :href="post.url" class="">
                            <h5 class="itl-title"><span>{{ind + 1}}.</span>{{post.title}}</h5>
                        </a>
                        <h6 class="">{{post.year[0]}}</h6>

                        <p v-if="post.content" class="itl-poster-desc">
                            {{post.content}}
                        </p>
                        <div class="review">
                            <h6 v-if="post.rating" class="">Rating: {{post.rating}}</h6>
                            <h6 v-if="post.vote_count" class="">Votes <span class="">{{post.vote_count}}</span></h6>
                        </div>

                    </div>
                </li>
            </ul>

            <!-- Grid View -->
            <div v-if="view_grid" class="itl-grid-view">
                <div v-for="(post,ind) in postsData" :key="ind" class="itl-grid-item youId"
                    :data-trailer-id="post.trailer_id[0]">
                    <div class="itl-grid-poster youPlay">
                        <a :href="post.url" class="">
                            <img :src="post.image" alt="poster" class="">
                        </a>
                    </div>
                    <div class="itl-grid-content">

                        <a :href="post.url" class="">
                            <h5 class="itl-title"><span>{{ind + 1}}.</span>{{post.title}}</h5>
                        </a>
                        <h6 class="">{{post.year[0]}}</h6>



                        <div class="itl-grid-footer">
                            <div class="review">
                                <h6 class="">Rating: {{post.rating}}</h6>
                                <h6 class="">Votes <span class="">{{post.vote_count}}</span></h6>
                            </div>
                            <a :href="post.url" class="">
                                <button class="itl-btn btn-st-1 btn-detail">Details</button>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Compact View -->
            <ul v-if="view_compact" class="itl-compact-view">
                <li v-for="(post,ind) in postsData" :key="ind" class="c-item youId"
                    :data-trailer-id="post.trailer_id[0]">
                    <div class="itl-poster">
                        <a :href="post.url" class="">
                            <img :src="post.image" alt="" class="">
                        </a>
                    </div>
                    <div class="itl-poster-content youPlay">
                        <a :href="post.url" class="">
                            <h5 class="itl-title"><span>{{ind + 1}}.</span> {{post.title}}</h5>
                        </a>
                        <h6 class="">{{post.year[0]}}</h6>
                        <div class="review">
                            <h6 class="">Rating: {{post.rating}}</h6>
                            <h6 class="">Votes <span class="">{{post.vote_count}}</span></h6>
                        </div>
                    </div>
                </li>
            </ul>


            <div class="load-more" v-if="page < maxPage">
                <div v-if="itlLoader" class="lds-ellipsis">
                    <div></div>
                    <div></div>
                    <div></div>
                    <div></div>
                </div>
                <button v-if="!itlLoader" id="itlLoadMore" @click="loadMoreContent()" class="itl-btn">Load
                    More</button>
            </div>

        </div>



        <!-- Empty grid image End -->
    </div>
</div>

<!-- ----------- -->
<!-- <ul id="itlSearchResult" class="itl-search-result">

            <?php
            if (isset($_GET['sq'])) {
                $searchQuery = $_GET['sq'];
                global $wpdb;
                $args = array(
                    'post_status' => 'publish',
                    'post_type' => array('tvshows', 'movies'),
                    'posts_per_page' => 10,
                    'paged' => 1,
                );
                if ($searchQuery != null) {
                    $args += array('s' => $searchQuery,);
                    $args['search_title'] = true;
                }

                $loop = new WP_Query($args);
            ?>
                <div class="count-status">
                    <div class="post-count"><span>1</span>-<span id="currentCount">10</span> of <span id="totalCount">
                            <?php echo ($loop->found_posts); ?>
                        </span>
                    </div>
                    <div class="itl-toolbar">
                        <div class="itl-sort">
                            <span class="ipc-simple-select__container"><label class="ipc-simple-select__front-label"
                                    for="adv-srch-sort-by">Sort by</label>
                                <span class="ipc-simple-select ipc-simple-select--base ipc-simple-select--on-accent2">
                                    <select :disabled="postsData.length == 1" id="adv-srch-sort-by" aria-label="Sort by"
                                        @change="changeSort()" v-model="sortSelected" class="ipc-simple-select__input">
                                        <option value=""></option>
                                        <option value="popularity">Popularity</option>
                                        <option value="title">A-Z</option>
                                        <option value="rating">Rating</option>
                                        <option value="release_date">Release Date</option>


                                    </select></span></span>
                            <button data-testid="test-sort-order" id="adv-srch-sort-order" class="itl-btn-st-1"
                                :class="{active: sortOrder=='DESC'}" @click="changeSortType($event)" title="Sort order"
                                role="button" tabindex="0" aria-label="Sort order" :disabled="postsData.length == 1">
                                <svg width="24" height="24" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                    fill="currentColor" role="presentation">
                                    <path fill="none" d="M0 0h24v24H0V0z"></path>
                                    <path
                                        d="M16 17.01V11c0-.55-.45-1-1-1s-1 .45-1 1v6.01h-1.79c-.45 0-.67.54-.35.85l2.79 2.78c.2.19.51.19.71 0l2.79-2.78c.32-.31.09-.85-.35-.85H16zM8.65 3.35L5.86 6.14c-.32.31-.1.85.35.85H8V13c0 .55.45 1 1 1s1-.45 1-1V6.99h1.79c.45 0 .67-.54.35-.85L9.35 3.35a.501.501 0 0 0-.7 0z">
                                    </path>
                                </svg>
                            </button>
                        </div>
                        <div class="itl-layout">

                            <button id="view_detial" @click="changeLayout('view_detial')"
                                class="itl-btn-st-1 active ipc-icon-button ipc-icon-button--base ipc-icon-button--onAccent2"
                                title="Selected:  Detailed view" role="button" tabindex="0" aria-label="Selected:  Detailed view"
                                aria-disabled="false">
                                <svg width="24" height="24" xmlns="http://www.w3.org/2000/svg"
                                    class="ipc-icon ipc-icon--list-inline" viewBox="0 0 24 24" fill="currentColor"
                                    role="presentation">
                                    <path
                                        d="M1.5 13.5c.825 0 1.5-.675 1.5-1.5s-.675-1.5-1.5-1.5S0 11.175 0 12s.675 1.5 1.5 1.5zm0 5c.825 0 1.5-.675 1.5-1.5s-.675-1.5-1.5-1.5S0 16.175 0 17s.675 1.5 1.5 1.5zm0-10C2.325 8.5 3 7.825 3 7s-.675-1.5-1.5-1.5S0 6.175 0 7s.675 1.5 1.5 1.5zm4.857 5h16.286c.746 0 1.357-.675 1.357-1.5s-.61-1.5-1.357-1.5H6.357C5.611 10.5 5 11.175 5 12s.61 1.5 1.357 1.5zm0 5h16.286c.746 0 1.357-.675 1.357-1.5s-.61-1.5-1.357-1.5H6.357C5.611 15.5 5 16.175 5 17s.61 1.5 1.357 1.5zM5 7c0 .825.61 1.5 1.357 1.5h16.286C23.389 8.5 24 7.825 24 7s-.61-1.5-1.357-1.5H6.357C5.611 5.5 5 6.175 5 7zm-3.5 6.5c.825 0 1.5-.675 1.5-1.5s-.675-1.5-1.5-1.5S0 11.175 0 12s.675 1.5 1.5 1.5zm0 5c.825 0 1.5-.675 1.5-1.5s-.675-1.5-1.5-1.5S0 16.175 0 17s.675 1.5 1.5 1.5zm0-10C2.325 8.5 3 7.825 3 7s-.675-1.5-1.5-1.5S0 6.175 0 7s.675 1.5 1.5 1.5zm4.857 5h16.286c.746 0 1.357-.675 1.357-1.5s-.61-1.5-1.357-1.5H6.357C5.611 10.5 5 11.175 5 12s.61 1.5 1.357 1.5zm0 5h16.286c.746 0 1.357-.675 1.357-1.5s-.61-1.5-1.357-1.5H6.357C5.611 15.5 5 16.175 5 17s.61 1.5 1.357 1.5zM5 7c0 .825.61 1.5 1.357 1.5h16.286C23.389 8.5 24 7.825 24 7s-.61-1.5-1.357-1.5H6.357C5.611 5.5 5 6.175 5 7z">
                                    </path>
                                </svg>
                            </button>
                            <button id="view_grid" @click="changeLayout('view_grid')"
                                class="itl-btn-st-1 ipc-icon-button ipc-icon-button--base ipc-icon-button--onBase"
                                title=" Grid view" role="button" tabindex="0" aria-label=" Grid view" aria-disabled="false"><svg
                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" class="ipc-icon ipc-icon--grid-view"
                                    viewBox="0 0 24 24" fill="currentColor" role="presentation">
                                    <path
                                        d="M4.8 14h2.4c.44 0 .8-.3.8-.667v-2.666C8 10.3 7.64 10 7.2 10H4.8c-.44 0-.8.3-.8.667v2.666c0 .367.36.667.8.667zm0-6h2.4c.44 0 .8-.3.8-.667V4.667C8 4.3 7.64 4 7.2 4H4.8c-.44 0-.8.3-.8.667v2.666C4 7.7 4.36 8 4.8 8zm0 12h2.4c.44 0 .8-.3.8-.667v-2.666C8 16.3 7.64 16 7.2 16H4.8c-.44 0-.8.3-.8.667v2.666c0 .367.36.667.8.667zm6 0h2.4c.44 0 .8-.3.8-.667v-2.666c0-.367-.36-.667-.8-.667h-2.4c-.44 0-.8.3-.8.667v2.666c0 .367.36.667.8.667zm6 0h2.4c.44 0 .8-.3.8-.667v-2.666c0-.367-.36-.667-.8-.667h-2.4c-.44 0-.8.3-.8.667v2.666c0 .367.36.667.8.667zm-6-6h2.4c.44 0 .8-.3.8-.667v-2.666c0-.367-.36-.667-.8-.667h-2.4c-.44 0-.8.3-.8.667v2.666c0 .367.36.667.8.667zm0-6h2.4c.44 0 .8-.3.8-.667V4.667C14 4.3 13.64 4 13.2 4h-2.4c-.44 0-.8.3-.8.667v2.666c0 .367.36.667.8.667zm5.2 2.667v2.666c0 .367.36.667.8.667h2.4c.44 0 .8-.3.8-.667v-2.666c0-.367-.36-.667-.8-.667h-2.4c-.44 0-.8.3-.8.667zm0-6v2.666c0 .367.36.667.8.667h2.4c.44 0 .8-.3.8-.667V4.667C20 4.3 19.64 4 19.2 4h-2.4c-.44 0-.8.3-.8.667z">
                                    </path>
                                </svg>
                            </button>
                            <button id="view_compact" @click="changeLayout('view_compact')"
                                class="itl-btn-st-1 ipc-icon-button ipc-icon-button--base ipc-icon-button--onBase"
                                title=" Compact view" role="button" tabindex="0" aria-label=" Compact view"
                                aria-disabled="false"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                    class="ipc-icon ipc-icon--menu" viewBox="0 0 24 24" fill="currentColor" role="presentation">
                                    <path fill="none" d="M0 0h24v24H0V0z"></path>
                                    <path
                                        d="M4 18h16c.55 0 1-.45 1-1s-.45-1-1-1H4c-.55 0-1 .45-1 1s.45 1 1 1zm0-5h16c.55 0 1-.45 1-1s-.45-1-1-1H4c-.55 0-1 .45-1 1s.45 1 1 1zM3 7c0 .55.45 1 1 1h16c.55 0 1-.45 1-1s-.45-1-1-1H4c-.55 0-1 .45-1 1z">
                                    </path>
                                </svg>
                            </button>

                        </div>
                    </div>
                </div>
                <?php
                while ($loop->have_posts()) {
                    $loop->the_post();
                    $post_id = get_the_ID();

                    $imdbRating = get_post_meta($post_id, 'imdbRating', true);
                    $viewsCount = get_post_meta($post_id, 'dt_views_count', true);
                    $runtime = get_post_meta($post_id, 'runtime', true);
                    $releaseDate = get_post_meta($post_id, 'release_date', true);
                    $voteCount = get_post_meta($post_id, 'vote_count', true);
                    $content = get_post_field('post_content', $post_id, true);
                    $content = wp_strip_all_tags($content);
                    // Remove HTML tags and limit to 50 words
                    $content = html_entity_decode(wp_trim_words($content, 45));




                    // Genres
                    $terms_genres = get_the_terms($post_id, 'genres');

                    // Check if there are any terms
                    $post_genres = [];
                    if ($terms_genres && !is_wp_error($terms_genres)) {
                        foreach ($terms_genres as $term) {
                            $term_name = $term->name;
                            $term_slug = $term->slug;
                            array_push($post_genres, $term_name);
                        }
                    }

                    // Director
                    $terms_directors = get_the_terms($post_id, 'dtdirector');

                    // Check if there are any terms
                    $post_directors = [];
                    if ($terms_directors && !is_wp_error($terms_directors)) {
                        foreach ($terms_directors as $term) {
                            $term_name = $term->name;
                            $term_slug = $term->slug;
                            array_push($post_directors, $term_name);
                        }
                    }

                    // Studio
                    $terms_studio = get_the_terms($post_id, 'dtstudio');

                    // Check if there are any terms
                    $post_studio = [];
                    if ($terms_studio && !is_wp_error($terms_studio)) {
                        foreach ($terms_studio as $term) {
                            $term_name = $term->name;
                            $term_slug = $term->slug;
                            array_push($post_studio, $term_name);
                        }
                    }

                    // Networks
                    $terms_networks = get_the_terms($post_id, 'dtnetworks');

                    // Check if there are any terms
                    $post_networks = [];
                    if ($terms_networks && !is_wp_error($terms_networks)) {
                        foreach ($terms_networks as $term) {
                            $term_name = $term->name;
                            $term_slug = $term->slug;
                            array_push($post_networks, $term_name);
                        }
                    }

                    // Years
                    $terms_years = get_the_terms($post_id, 'dtyear');

                    // Check if there are any terms
                    $post_years = [];
                    if ($terms_years && !is_wp_error($terms_years)) {
                        foreach ($terms_years as $term) {
                            $term_name = $term->name;
                            $term_slug = $term->slug;
                            array_push($post_years, $term_name);
                        }
                    }

                    // Tags
                    $terms_tags = get_the_terms($post_id, 'post_tag');

                    // Check if there are any terms
                    $post_tags = [];
                    if ($terms_tags && !is_wp_error($terms_tags)) {
                        foreach ($terms_tags as $term) {
                            $term_name = $term->name;
                            $term_slug = $term->slug;
                            array_push($post_tags, $term_name);
                        }
                    }


                    // Casts
                    $terms_casts = get_the_terms($post_id, 'dtcast');


                    $post_casts = [];
                    if ($terms_casts && !is_wp_error($terms_casts)) {
                        foreach ($terms_casts as $term) {
                            $term_name = $term->name;
                            $term_slug = $term->slug;
                            array_push($post_casts, $term_name);
                        }
                    }

                    // Get the post type
                    $p_type = get_post_type();
                ?>

                    <li>
                        <a href="<?php echo get_permalink($post_id); ?>">
                            <div class="itl-q-main">
                                <div class="image">
                                    <img src="<?php echo get_the_post_thumbnail_url($post_id); ?>" alt="">
                                </div>
                                <div class="itl-q-content itl-poster-content">
                                    <h5>
                                        <?php echo html_entity_decode(get_the_title()); ?>
                                    </h5>
                                    <?php if (count($post_years) > 0) { ?>
                                        <h6>
                                            <?php echo $post_years[0]; ?>
                                        </h6>
                                    <?php } ?>
                                    <?php if ($content != '') { ?>
                                        <p class="itl-poster-desc">
                                            <?php echo $content; ?>
                                        </p>
                                    <?php } ?>
                                    <div class="review">
                                        <?php if ($imdbRating != '') { ?>
                                            <h6>Rating:
                                                <?php echo $imdbRating; ?>
                                            </h6>
                                        <?php } ?>
                                        <?php if ($voteCount != '') { ?>
                                            <h6>Votes <span>
                                                    <?php echo $voteCount; ?>
                                                </span></h6>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </li>


                    <?php

                    // $associative = [
                    //     'id' => $post_id,
                    //     'url' => get_permalink($post_id),
                    //     'image' => get_the_post_thumbnail_url($post_id),
                    //     'title' => html_entity_decode(get_the_title()),
                    //     'genres' => $post_genres,
                    //     'directors' => $post_directors,
                    //     'year' => $post_years,
                    //     'tags' => $post_tags,
                    //     'rating' => $imdbRating,
                    //     'cast' => $post_casts,
                    //     'post_type' => $p_type,
                    //     'studio' => $post_studio,
                    //     'networks' => $post_networks,
                    // ];

                    // $post_data[] = $associative;
                }
                wp_reset_postdata();
                // echo $loop->max_num_pages;
                    ?>

            </ul> -->
<!-- <?php if ($loop->found_posts > 1) { ?>
                <div class="lds-ellipsis sr-loader">
                    <div></div>
                    <div></div>
                    <div></div>
                    <div></div>
                </div>
                <button data-page="1" data-max_page="<?php echo $loop->found_posts; ?>" id="load-more-button"
                    class="btn-load btn-st-1">Load More</button>
            <?php } ?> -->
<?php

            } else {
                echo 'No search query provided.';
            }
    ?>




<!-- </div> -->
<?php
        return ob_get_clean();
    }


    function get_movies_data()
    {
        $page = isset($_POST['page']) ? $_POST['page'] : 1;
        // Define default values or set to null if not provided
        $title = isset($_POST['title']) ? $_POST['title'] : null;
        $studio = isset($_POST['studio']) ? $_POST['studio'] : null;
        $genre = isset($_POST['genre']) ? $_POST['genre'] : null;
        $tag = isset($_POST['tag']) ? $_POST['tag'] : null;
        $cast = isset($_POST['cast']) ? $_POST['cast'] : null;
        $languages = isset($_POST['languages']) ? $_POST['languages'] : null;
        $sp = isset($_POST['sp']) ? $_POST['sp'] : null;
        $year = isset($_POST['year']) ? $_POST['year'] : null;
        $director = isset($_POST['director']) ? $_POST['director'] : null;
        $network = isset($_POST['network']) ? $_POST['network'] : null;
        $rating = isset($_POST['rating']) ? $_POST['rating'] : null;
        $runtime = isset($_POST['runtime']) ? $_POST['runtime'] : null;
        $awards = isset($_POST['awards']) ? $_POST['awards'] : null;
        $votes = isset($_POST['votes']) ? $_POST['votes'] : null;
        $spcontent = isset($_POST['spcontent']) ? $_POST['spcontent'] : null;
        $comment = isset($_POST['comment']) ? $_POST['comment'] : null;
        $ttype = isset($_POST['ttype']) ? $_POST['ttype'] : null;
        $companies = isset($_POST['companies']) ? $_POST['companies'] : null;
        $certificates = isset($_POST['certificates']) ? $_POST['certificates'] : null;

        $sort = isset($_POST['sort']) ? $_POST['sort'] : null;
        $sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : null;

        $genre = !empty($genre) ? explode(',', $genre) : array();
        $ttype = !empty($ttype) ? explode(',', $ttype) : array();
        $companies = !empty($companies) ? explode(',', $companies) : array();
        $certificates = !empty($certificates) ? explode(',', $certificates) : array();
        $awards = !empty($awards) ? explode(',', $awards) : array();
        $tag = !empty($tag) ? explode(',', $tag) : array();
        $rating = !empty($rating) ? explode(',', $rating) : array();
        $votes = !empty($votes) ? explode(',', $votes) : array();
        $runtime = !empty($runtime) ? explode(',', $runtime) : array();
        $cast = !empty($cast) ? explode(',', $cast) : array();
        $languages = !empty($languages) ? explode(',', $languages) : array();
        $sp = !empty($sp) ? explode(',', $sp) : array();

        // Define the mappings of variables to taxonomy query parameters
        $tax_query = array(
            'studio' => array('taxonomy' => 'dtstudio', 'field' => 'slug'),
            'genre' => array('taxonomy' => 'genres', 'field' => 'slug'),
            'ttype' => array('taxonomy' => 'ttype', 'field' => 'slug'),
            'companies' => array('taxonomy' => 'companies', 'field' => 'slug'),
            'certificates' => array('taxonomy' => 'certificates', 'field' => 'slug'),
            'tag' => array('taxonomy' => 'post_tag', 'field' => 'slug'),
            'cast' => array('taxonomy' => 'dtcast', 'field' => 'slug'),
            'languages' => array('taxonomy' => 'languages', 'field' => 'slug'),
            'sp' => array('taxonomy' => 'streaming_p', 'field' => 'slug'),
            'year' => array('taxonomy' => 'dtyear', 'field' => 'slug'),
            'director' => array('taxonomy' => 'dtdirector', 'field' => 'slug'),
            'network' => array('taxonomy' => 'dtnetworks', 'field' => 'slug'),
            'awards' => array('taxonomy' => 'awards', 'field' => 'slug'),
        );
        $tx_query = array();
        foreach ($tax_query as $var => $taxonomy_params) {
            if ($$var != null) {
                if ($var == 'genre') {
                    $op = 'AND';
                } else {
                    $op = 'IN';
                }
                $tx_query[] = array(
                    'taxonomy' => $taxonomy_params['taxonomy'],
                    'field' => $taxonomy_params['field'],
                    'terms' => $$var,
                    'operator' => $op, // You can customize this operator if needed
                );
            }
        }

        $args = array(
            'post_status' => 'publish',
            'post_type' => array('tvshows', 'movies'),
            'posts_per_page' => 10,
            'paged' => $page,
        );

        // Sort Title
        if ($sort != null and $sort == 'title') {
            $args['orderby'] = 'title';
            $args['order'] = $sort_order;
        }
        // Sort Rating
        if ($sort != null and $sort == 'rating') {
            $args['orderby'] = 'meta_value_num';
            $args['order'] = $sort_order;
            $args['meta_key'] = 'imdbRating';
        }
        // Sort Popularity
        if ($sort != null and $sort == 'popularity') {
            $args['orderby'] = 'meta_value_num';
            $args['order'] = $sort_order;
            $args['meta_key'] = 'dt_views_count';
        }
        // Sort release_date
        if ($sort != null and $sort == 'release_date') {
            $args['orderby'] = 'meta_value';
            $args['order'] = $sort_order;
            $args['meta_key'] = 'release_date';
        }
        // Year Sort
        // if ($sort != null and $sort == 'year') {
        //     $tx_query[] = array(
        //         'taxonomy' => 'dtyear',
        //         'terms' => '',
        //         'operator' => 'NOT IN',
        //         'field' => 'slug',
        //         'orderby' => 'term_id',
        //         'order' => $sort_order,
        //     );
        // }

        if ($title != null) {
            $args += array('s' => $title,);
            $args['search_title'] = true;
        }

        $meta_query = array();

        if (!empty($rating) && is_array($rating)) {
            if (count($rating) === 1) {
                $compare_operator = '=';
            } else {
                $compare_operator = 'BETWEEN';
            }
            $meta_query[] = array(
                'key' => 'imdbRating',
                'value' => $rating,
                'compare' => $compare_operator,
                'type' => 'NUMERIC',
            );
        }

        if (!empty($votes) && is_array($votes)) {
            if (count($votes) === 1) {
                $compare_operator = '=';
            } else {
                $compare_operator = 'BETWEEN';
            }
            $meta_query[] = array(
                'key' => 'vote_count',
                'value' => $votes,
                'compare' => $compare_operator,
                'type' => 'NUMERIC',
            );
        }

        if (!empty($runtime) && is_array($runtime)) {
            if (count($runtime) === 1) {
                $compare_operator = '=';
            } else {
                $compare_operator = 'BETWEEN';
            }
            $meta_query[] = array(
                'key' => 'runtime',
                'value' => $runtime,
                'compare' => $compare_operator,
                'type' => 'NUMERIC',
            );
        }


        if (!empty($spcontent)) {

            $compare_operator = 'LIKE';

            $meta_query[] = array(
                'key' => 'itl_post_content',
                'value' => $spcontent,
                'compare' => $compare_operator,
            );
        }

        if (!empty($comment)) {

            $compare_operator = 'LIKE';
            $meta_query[] = array(
                'key' => 'itl_post_comment',
                'value' => $comment,
                'compare' => $compare_operator,
            );
        }



        if (count($meta_query) > 0) {
            $meta_query['relation'] = 'OR';
            $args['meta_query'] = $meta_query;
        }
        // if (count($tx_query) > 1) {
        //     $tx_query['relation'] = 'AND';
        // }
        if (count($tx_query) > 0) {
            $tx_query['relation'] = 'AND';
            $args += array('tax_query' => $tx_query);
        }



        // $args = array(
        //     'post_status' => 'publish',
        //     'post_type' => 'movies',
        //     'posts_per_page' => 10,
        //     'tax_query' => array(
        //         array(
        //             'taxonomy' => 'genres',
        //             'field' => 'name',
        //             'terms' => $title,
        //             'operator' => 'IN', // You can customize this operator if needed
        //         )
        //     ),
        // );


        $loop = new WP_Query($args);

        while ($loop->have_posts()) {
            $loop->the_post();
            $post_id = get_the_ID();

            $imdbRating = get_post_meta($post_id, 'imdbRating', true);
            $viewsCount = get_post_meta($post_id, 'dt_views_count', true);
            $runtime = get_post_meta($post_id, 'runtime', true);
            $releaseDate = get_post_meta($post_id, 'release_date', true);
            $voteCount = get_post_meta($post_id, 'vote_count', true);
            $trailer_id = get_post_meta($post_id, "youtube_id");
            $trailer_id = str_replace(array('[', ']'), '', $trailer_id);
            $content = get_post_field('post_content', $post_id, true);
            // Check if $content contains $spcontent
            $content = wp_strip_all_tags($content);
            // Remove HTML tags and limit to 50 words
            $content = html_entity_decode(wp_trim_words($content, 25));
            // Genres
            $terms_genres = get_the_terms($post_id, 'genres');
            // Check if there are any terms
            $post_genres = [];
            if ($terms_genres && !is_wp_error($terms_genres)) {
                foreach ($terms_genres as $term) {
                    $term_name = $term->name;
                    $term_slug = $term->slug;
                    array_push($post_genres, $term_name);
                }
            }
            // Awards
            $terms_awards = get_the_terms($post_id, 'awards');
            // Check if there are any terms
            $post_awards = [];
            if ($terms_awards && !is_wp_error($terms_awards)) {
                foreach ($terms_awards as $term) {
                    $term_name = $term->name;
                    $term_slug = $term->slug;
                    array_push($post_awards, $term_name);
                }
            }

            // Director
            $terms_directors = get_the_terms($post_id, 'dtdirector');
            // Check if there are any terms
            $post_directors = [];
            if ($terms_directors && !is_wp_error($terms_directors)) {
                foreach ($terms_directors as $term) {
                    $term_name = $term->name;
                    $term_slug = $term->slug;
                    array_push($post_directors, $term_name);
                }
            }
            // Studio
            $terms_studio = get_the_terms($post_id, 'dtstudio');
            // Check if there are any terms
            $post_studio = [];
            if ($terms_studio && !is_wp_error($terms_studio)) {
                foreach ($terms_studio as $term) {
                    $term_name = $term->name;
                    $term_slug = $term->slug;
                    array_push($post_studio, $term_name);
                }
            }
            // Networks
            $terms_networks = get_the_terms($post_id, 'dtnetworks');

            // Check if there are any terms
            $post_networks = [];
            if ($terms_networks && !is_wp_error($terms_networks)) {
                foreach ($terms_networks as $term) {
                    $term_name = $term->name;
                    $term_slug = $term->slug;
                    array_push($post_networks, $term_name);
                }
            }

            // Years
            $terms_years = get_the_terms($post_id, 'dtyear');

            // Check if there are any terms
            $post_years = [];
            if ($terms_years && !is_wp_error($terms_years)) {
                foreach ($terms_years as $term) {
                    $term_name = $term->name;
                    $term_slug = $term->slug;
                    array_push($post_years, $term_name);
                }
            }

            // Tags
            $terms_tags = get_the_terms($post_id, 'post_tag');

            // Check if there are any terms
            $post_tags = [];
            if ($terms_tags && !is_wp_error($terms_tags)) {
                foreach ($terms_tags as $term) {
                    $term_name = $term->name;
                    $term_slug = $term->slug;
                    array_push($post_tags, $term_name);
                }
            }


            // Casts
            $terms_casts = get_the_terms($post_id, 'dtcast');


            $post_casts = [];
            if ($terms_casts && !is_wp_error($terms_casts)) {
                foreach ($terms_casts as $term) {
                    $term_name = $term->name;
                    $term_slug = $term->slug;
                    array_push($post_casts, $term_name);
                }
            }

            // Get the post type
            $p_type = get_post_type();


            $associative = [
                'id' => $post_id,
                'url' => get_permalink($post_id),
                'image' => get_the_post_thumbnail_url($post_id),
                'title' => html_entity_decode(get_the_title()),
                'genres' => $post_genres,
                'directors' => $post_directors,
                'year' => $post_years,
                'tags' => $post_tags,
                'rating' => $imdbRating,
                'cast' => $post_casts,
                'post_type' => $p_type,
                'studio' => $post_studio,
                'networks' => $post_networks,
                'views_count' => $viewsCount,
                'release_date' => $releaseDate,
                'vote_count' => $voteCount,
                'content' => $content,
                'awards' => $post_awards,
                'runtime' => $runtime,
                'trailer_id' => $trailer_id,
            ];

            $post_data[] = $associative;
        }
        wp_reset_postdata();


        // Return JSON response
        echo json_encode(
            array(
                'postData' => $post_data,
                'maxPage' => $loop->max_num_pages,
                'fposts' => $loop->found_posts,
                '$args' => $args,
            )
        );

        wp_die();
    }

    function get_fields_data()
    {
        // GET Genres LIST
        $g_terms = get_terms(
            array(
                'taxonomy' => 'genres',
                'hide_empty' => false,
            )
        );

        $genres_terms = array();

        foreach ($g_terms as $term) {
            if ($term->parent === 0) {
                $term_object = array(
                    'name' => html_entity_decode($term->name),
                    'id' => $term->term_id,
                    'count' => $term->count,
                    'slug' => $term->slug,
                );
                $genres_terms[] = $term_object;
            }
        }

        // GET Tags LIST
        $t_terms = get_terms(
            array(
                'taxonomy' => 'post_tag',
                'hide_empty' => false,
            )
        );

        $tag_terms = array();

        foreach ($t_terms as $term) {
            if ($term->parent === 0) {
                $term_object = array(
                    'name' => html_entity_decode($term->name),
                    'id' => $term->term_id,
                    'count' => $term->count,
                    'slug' => $term->slug,
                );
                $tag_terms[] = $term_object;
            }
        }

        // GET Awards LIST
        $aw_terms = get_terms(
            array(
                'taxonomy' => 'awards',
                'hide_empty' => false,
            )
        );

        $awards_terms = array();

        foreach ($aw_terms as $term) {
            if ($term->parent === 0) {
                $term_object = array(
                    'name' => html_entity_decode($term->name),
                    'id' => $term->term_id,
                    'count' => $term->count,
                    'slug' => $term->slug,
                );
                $awards_terms[] = $term_object;
            }
        }


        // GET Title Type LIST
        $ttype_terms = get_terms(
            array(
                'taxonomy' => 'ttype',
                'hide_empty' => false,
            )
        );

        $tt_terms = array();

        foreach ($ttype_terms as $term) {
            if ($term->parent === 0) {
                $term_object = array(
                    'name' => html_entity_decode($term->name),
                    'id' => $term->term_id,
                    'count' => $term->count,
                    'slug' => $term->slug,
                );
                $tt_terms[] = $term_object;
            }
        }

        // GET Companies Type LIST
        $companies_terms = get_terms(
            array(
                'taxonomy' => 'companies',
                'hide_empty' => false,
            )
        );

        $comp_terms = array();

        foreach ($companies_terms as $term) {
            if ($term->parent === 0) {
                $term_object = array(
                    'name' => html_entity_decode($term->name),
                    'id' => $term->term_id,
                    'count' => $term->count,
                    'slug' => $term->slug,
                );
                $comp_terms[] = $term_object;
            }
        }

        // GET Certificates Type LIST
        $certificates_terms = get_terms(
            array(
                'taxonomy' => 'certificates',
                'hide_empty' => false,
            )
        );

        $certi_terms = array();

        foreach ($certificates_terms as $term) {
            if ($term->parent === 0) {
                $term_object = array(
                    'name' => html_entity_decode($term->name),
                    'id' => $term->term_id,
                    'count' => $term->count,
                    'slug' => $term->slug,
                );
                $certi_terms[] = $term_object;
            }
        }

        // GET Languages Type LIST
        $language_terms = get_terms(
            array(
                'taxonomy' => 'languages',
                'hide_empty' => false,
            )
        );

        $lang_terms = array();

        foreach ($language_terms as $term) {
            if ($term->parent === 0) {
                $term_object = array(
                    'name' => html_entity_decode($term->name),
                    'id' => $term->term_id,
                    'count' => $term->count,
                    'slug' => $term->slug,
                );
                $lang_terms[] = $term_object;
            }
        }

        // GET Streaming Platform LIST
        $sp_terms = get_terms(
            array(
                'taxonomy' => 'streaming_p',
                'hide_empty' => false,
            )
        );

        $s_terms = array();

        foreach ($sp_terms as $term) {
            if ($term->parent === 0) {
                $term_object = array(
                    'name' => html_entity_decode($term->name),
                    'id' => $term->term_id,
                    'count' => $term->count,
                    'slug' => $term->slug,
                );
                $s_terms[] = $term_object;
            }
        }

        // -----------------------------------------------------------------------------

        // Return JSON response
        echo json_encode(
            array(
                'genres' => $genres_terms,
                'tags' => $tag_terms,
                'awards' => $awards_terms,
                'ttype' => $tt_terms,
                'companies' => $comp_terms,
                'certificates' => $certi_terms,
                'lang' => $lang_terms,
                'sp' => $s_terms,
            )
        );

        wp_die();
    }

    function get_cast_data()
    {
        $query_string = $_POST['data'];
        global $wpdb;
        // $terms = get_terms(array(
        //     'taxonomy' => 'dtcast',
        //     'name__like' => $query_string,
        //     'hide_empty' => false, // Set to true if you want to exclude empty terms
        // ));

        $terms = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
              FROM {$wpdb->terms} t
              INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
              WHERE tt.taxonomy = 'dtcast' AND t.name LIKE %s",
                $query_string . '%'
            )
        );


        $cast_terms = array();
        foreach ($terms as $term) {
            // Customize this as per your requirements
            $image = get_term_meta($term->term_id, 'cast-image', true);
            $cast_terms[] = array(
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'image' => $image,
                'des' => $term->description,
            );
        }

        // Return JSON response
        echo json_encode(
            array(
                'castTerms' => $cast_terms,
                'query_string' => $query_string,
                '$terms' => $terms,
            )
        );

        wp_die();
    }

    function get_search_data()
    {
        $query_string = $_POST['data'];
        global $wpdb;

        $args = array(
            'post_status' => 'publish',
            'post_type' => array('tvshows', 'movies'),
            'posts_per_page' => 10,
            'paged' => 1,

        );

        if ($query_string != null) {
            $args += array('s' => $query_string,);
            $args['search_title'] = true;
        }


        $loop = new WP_Query($args);
        while ($loop->have_posts()) {
            $loop->the_post();
            $post_id = get_the_ID();

            $imdbRating = get_post_meta($post_id, 'imdbRating', true);
            $viewsCount = get_post_meta($post_id, 'dt_views_count', true);
            $runtime = get_post_meta($post_id, 'runtime', true);
            $releaseDate = get_post_meta($post_id, 'release_date', true);
            $voteCount = get_post_meta($post_id, 'vote_count', true);
            $trailer_id = get_post_meta($post_id, "youtube_id");
            $trailer_id = str_replace(array('[', ']'), '', $trailer_id);
            $content = get_post_field('post_content', $post_id, true);
            $content = wp_strip_all_tags($content);
            // Remove HTML tags and limit to 50 words
            $content = html_entity_decode(wp_trim_words($content, 25));


            // Genres
            $terms_genres = get_the_terms($post_id, 'genres');

            // Check if there are any terms
            $post_genres = [];
            if ($terms_genres && !is_wp_error($terms_genres)) {
                foreach ($terms_genres as $term) {
                    $term_name = $term->name;
                    $term_slug = $term->slug;
                    array_push($post_genres, $term_name);
                }
            }

            // Awards
            $terms_awards = get_the_terms($post_id, 'awards');

            // Check if there are any terms
            $post_awards = [];
            if ($terms_awards && !is_wp_error($terms_awards)) {
                foreach ($terms_awards as $term) {
                    $term_name = $term->name;
                    $term_slug = $term->slug;
                    array_push($post_awards, $term_name);
                }
            }

            // Director
            $terms_directors = get_the_terms($post_id, 'dtdirector');

            // Check if there are any terms
            $post_directors = [];
            if ($terms_directors && !is_wp_error($terms_directors)) {
                foreach ($terms_directors as $term) {
                    $term_name = $term->name;
                    $term_slug = $term->slug;
                    array_push($post_directors, $term_name);
                }
            }

            // Studio
            $terms_studio = get_the_terms($post_id, 'dtstudio');

            // Check if there are any terms
            $post_studio = [];
            if ($terms_studio && !is_wp_error($terms_studio)) {
                foreach ($terms_studio as $term) {
                    $term_name = $term->name;
                    $term_slug = $term->slug;
                    array_push($post_studio, $term_name);
                }
            }

            // Networks
            $terms_networks = get_the_terms($post_id, 'dtnetworks');

            // Check if there are any terms
            $post_networks = [];
            if ($terms_networks && !is_wp_error($terms_networks)) {
                foreach ($terms_networks as $term) {
                    $term_name = $term->name;
                    $term_slug = $term->slug;
                    array_push($post_networks, $term_name);
                }
            }

            // Years
            $terms_years = get_the_terms($post_id, 'dtyear');

            // Check if there are any terms
            $post_years = [];
            if ($terms_years && !is_wp_error($terms_years)) {
                foreach ($terms_years as $term) {
                    $term_name = $term->name;
                    $term_slug = $term->slug;
                    array_push($post_years, $term_name);
                }
            }

            // Tags
            $terms_tags = get_the_terms($post_id, 'post_tag');

            // Check if there are any terms
            $post_tags = [];
            if ($terms_tags && !is_wp_error($terms_tags)) {
                foreach ($terms_tags as $term) {
                    $term_name = $term->name;
                    $term_slug = $term->slug;
                    array_push($post_tags, $term_name);
                }
            }


            // Casts
            $terms_casts = get_the_terms($post_id, 'dtcast');


            $post_casts = [];
            if ($terms_casts && !is_wp_error($terms_casts)) {
                foreach ($terms_casts as $term) {
                    $term_name = $term->name;
                    $term_slug = $term->slug;
                    array_push($post_casts, $term_name);
                }
            }

            // Get the post type
            $p_type = get_post_type();


            $associative = [
                'id' => $post_id,
                'url' => get_permalink($post_id),
                'image' => get_the_post_thumbnail_url($post_id),
                'title' => html_entity_decode(get_the_title()),
                'genres' => $post_genres,
                'directors' => $post_directors,
                'year' => $post_years,
                'tags' => $post_tags,
                'rating' => $imdbRating,
                'cast' => $post_casts,
                'post_type' => $p_type,
                'studio' => $post_studio,
                'networks' => $post_networks,
                'views_count' => $viewsCount,
                'release_date' => $releaseDate,
                'vote_count' => $voteCount,
                'awards' => $post_awards,
                'content' => $content,
                'runtime' => $runtime,
                'trailer_id' => $trailer_id,
            ];

            $post_data[] = $associative;
        }
        wp_reset_postdata();



        // Return JSON response
        echo json_encode(
            array(
                'data' => $post_data,
            )
        );

        wp_die();
    }


    function itl_get_hash_comments()
    {
        $query_comments = $_POST['data'];
        global $wpdb;

        $comments = $wpdb->get_results("SELECT * FROM $wpdb->comments WHERE comment_content LIKE '%#%'");




        // Return JSON response
        echo json_encode(
            array(
                'data' => $comments,
            )
        );

        wp_die();
    }


    // functions.php or custom plugin

    // For Search Result Page
    function load_more_posts()
    {
        global $wpdb;
        $page = $_POST['page'];
        $searchQuery = $_POST['sq'];

        $args = array(
            'post_status' => 'publish',
            'post_type' => array('tvshows', 'movies'),
            'posts_per_page' => 10,
            'paged' => $page,
        );


        if ($searchQuery != null) {
            $args += array('s' => $searchQuery,);
            $args['search_title'] = true;
        }

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                $imdbRating = get_post_meta($post_id, 'imdbRating', true);
                $viewsCount = get_post_meta($post_id, 'dt_views_count', true);
                $runtime = get_post_meta($post_id, 'runtime', true);
                $releaseDate = get_post_meta($post_id, 'release_date', true);
                $voteCount = get_post_meta($post_id, 'vote_count', true);
                $content = get_post_field('post_content', $post_id, true);
                $content = wp_strip_all_tags($content);
                // Remove HTML tags and limit to 50 words
                $content = html_entity_decode(wp_trim_words($content, 45));



                // Genres
                $terms_genres = get_the_terms($post_id, 'genres');

                // Check if there are any terms
                $post_genres = [];
                if ($terms_genres && !is_wp_error($terms_genres)) {
                    foreach ($terms_genres as $term) {
                        $term_name = $term->name;
                        $term_slug = $term->slug;
                        array_push($post_genres, $term_name);
                    }
                }



                // Awards
                $terms_awards = get_the_terms($post_id, 'awards');

                // Check if there are any terms
                $post_awards = [];
                if ($terms_awards && !is_wp_error($terms_awards)) {
                    foreach ($terms_awards as $term) {
                        $term_name = $term->name;
                        $term_slug = $term->slug;
                        array_push($post_awards, $term_name);
                    }
                } // Director
                $terms_directors = get_the_terms($post_id, 'dtdirector');

                // Check if there are any terms
                $post_directors = [];
                if ($terms_directors && !is_wp_error($terms_directors)) {
                    foreach ($terms_directors as $term) {
                        $term_name = $term->name;
                        $term_slug = $term->slug;
                        array_push($post_directors, $term_name);
                    }
                }

                // Studio
                $terms_studio = get_the_terms($post_id, 'dtstudio');

                // Check if there are any terms
                $post_studio = [];
                if ($terms_studio && !is_wp_error($terms_studio)) {
                    foreach ($terms_studio as $term) {
                        $term_name = $term->name;
                        $term_slug = $term->slug;
                        array_push($post_studio, $term_name);
                    }
                }

                // Networks
                $terms_networks = get_the_terms($post_id, 'dtnetworks');

                // Check if there are any terms
                $post_networks = [];
                if ($terms_networks && !is_wp_error($terms_networks)) {
                    foreach ($terms_networks as $term) {
                        $term_name = $term->name;
                        $term_slug = $term->slug;
                        array_push($post_networks, $term_name);
                    }
                }

                // Years
                $terms_years = get_the_terms($post_id, 'dtyear');

                // Check if there are any terms
                $post_years = [];
                if ($terms_years && !is_wp_error($terms_years)) {
                    foreach ($terms_years as $term) {
                        $term_name = $term->name;
                        $term_slug = $term->slug;
                        array_push($post_years, $term_name);
                    }
                }

                // Tags
                $terms_tags = get_the_terms($post_id, 'post_tag');

                // Check if there are any terms
                $post_tags = [];
                if ($terms_tags && !is_wp_error($terms_tags)) {
                    foreach ($terms_tags as $term) {
                        $term_name = $term->name;
                        $term_slug = $term->slug;
                        array_push($post_tags, $term_name);
                    }
                }


                // Casts
                $terms_casts = get_the_terms($post_id, 'dtcast');


                $post_casts = [];
                if ($terms_casts && !is_wp_error($terms_casts)) {
                    foreach ($terms_casts as $term) {
                        $term_name = $term->name;
                        $term_slug = $term->slug;
                        array_push($post_casts, $term_name);
                    }
                }

                // Get the post type
                $p_type = get_post_type();


                $associative = [
                    'id' => $post_id,
                    'url' => get_permalink($post_id),
                    'image' => get_the_post_thumbnail_url($post_id),
                    'title' => html_entity_decode(get_the_title()),
                    'genres' => $post_genres,
                    'directors' => $post_directors,
                    'year' => $post_years,
                    'tags' => $post_tags,
                    'rating' => $imdbRating,
                    'cast' => $post_casts,
                    'post_type' => $p_type,
                    'studio' => $post_studio,
                    'networks' => $post_networks,
                    'views_count' => $viewsCount,
                    'release_date' => $releaseDate,
                    'vote_count' => $voteCount,
                    'awards' => $post_awards,
                    'content' => $content,
                    'runtime' => $runtime,
                ];
                $post_data[] = $associative;
            }
            wp_reset_postdata();
        } else {
            $post_data = null;
        }



        // Return JSON response
        echo json_encode(
            array(
                'postData' => $post_data,
                '$args' => $args,
                'sq' => $searchQuery,
                'max' => $query->max_num_pages,
            )
        );
        wp_die();
    }


    // Add custom meta field to custom taxonomy term for a specific post type
    function add_taxonomy_image_field()
    {
        // Enqueue media scripts
        wp_enqueue_media();

?>
<div class="form-field term-group">
    <label for="cast-image">
        <?php _e('Cast Image', 'textdomain'); ?>
    </label>
    <input type="text" class="term-image" name="cast-image" id="cast-image" value="">

    <button class="button button-secondary category-image-upload" id="cast-image-upload">
        <?php _e('Upload Cast Image', 'textdomain'); ?>
    </button>
</div>
<script>
jQuery(document).ready(function($) {
    var file_frame;

    $(document).on('click', '#cast-image-upload', function(event) {
        event.preventDefault();

        if (file_frame) {
            file_frame.open();
            return;
        }

        // Ensure that wp.media is available and properly initialized
        if (typeof wp !== 'undefined' && wp.media) {
            file_frame = wp.media({
                title: $(this).data('uploader_title'),
                button: {
                    text: $(this).data('uploader_button_text'),
                },
                multiple: false
            });

            file_frame.on('select', function() {
                var attachment = file_frame.state().get('selection').first().toJSON();
                $('#cast-image').val(attachment.url);
            });

            file_frame.open();
        } else {
            console.error('wp.media is not available or properly initialized.');
        }
    });
});
</script>
<?php
    }
    // Add custom meta field to custom taxonomy term for a specific post type
    function edit_taxonomy_image_field($one, $two)
    {
        // Enqueue media scripts
        wp_enqueue_media();
        $term_id = $one->term_id;
        $image = get_term_meta($term_id, 'cast-image', true);
?>
<div class="form-field term-group">
    <label for="cast-image">
        <?php _e('Cast Image', 'textdomain'); ?>
    </label>

    <input type="hidden" class="term-image" name="cast-image" id="cast-image" value="<?php echo $image ?>">

    <?php


        if ($image) {
            echo '<img id="uimage" style="max-width: 60px; max-height: 60px;" src="' . esc_url($image) . '" alt="' . esc_attr(get_the_archive_title()) . '">';
        } else {
            echo '<img id="uimage" style="max-width: 60px; max-height: 60px;" src="" alt="">';
        }
        ?>
    <button class="button button-secondary category-image-upload" id="cast-image-upload">
        <?php _e('Edit Cast Image', 'textdomain'); ?>
    </button>
</div>
<script>
jQuery(document).ready(function($) {
    var file_frame;

    $(document).on('click', '#cast-image-upload', function(event) {
        event.preventDefault();

        if (file_frame) {
            file_frame.open();
            return;
        }

        // Ensure that wp.media is available and properly initialized
        if (typeof wp !== 'undefined' && wp.media) {
            file_frame = wp.media({
                title: $(this).data('uploader_title'),
                button: {
                    text: $(this).data('uploader_button_text'),
                },
                multiple: false
            });

            file_frame.on('select', function() {

                var attachment = file_frame.state().get('selection').first().toJSON();
                $('#cast-image').val(attachment.url);

                if ($('#uimage').length) {
                    $('#uimage').attr('src', attachment.url)
                } else {

                }
            });

            file_frame.open();
        } else {
            console.error('wp.media is not available or properly initialized.');
        }
    });
});
</script>
<?php
    }





    // Save custom meta field data for specific post type and taxonomy
    function save_taxonomy_image_field($term_id)
    {
        if (isset($_POST['cast-image'])) {
            $image = esc_url($_POST['cast-image']);
            update_term_meta($term_id, 'cast-image', $image);
        }
    }


    // Display taxonomy image on custom post type archive page
    function display_taxonomy_image()
    {
        $term_id = get_queried_object_id();
        $image = get_term_meta($term_id, 'cast-image', true);
        if ($image) {
            echo '<img  src="' . esc_url($image) . '" alt="' . esc_attr(get_the_archive_title()) . '">';
        }
    }


    /**
     * Helper Functions
     */
}

new WP_ITL_MOVIES_FILTER();


?>
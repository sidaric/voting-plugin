<?php
/*
Plugin Name: Article Feedback - Voting
Description: A plugin for voting on articles.
Version: 1.0
Author: Richard Sinka
*/

// Enqueue styles and scripts.
function enqueue_plugin_styles_and_scripts()
{
    wp_enqueue_style("plugin-style", plugin_dir_url(__FILE__) . "style.css");
    wp_enqueue_script(
        "plugin-script",
        plugin_dir_url(__FILE__) . "script.js",
        ["jquery"],
        "1.0",
        true
    );

    // Define AJAX URL for JavaScript.
    wp_localize_script("plugin-script", "voting_plugin_ajax", [
        "ajaxurl" => admin_url("admin-ajax.php"),
    ]);
}
add_action("wp_enqueue_scripts", "enqueue_plugin_styles_and_scripts");

// Create a custom table to store voting data.
function create_voting_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . "voting_data";

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id mediumint(9) NOT NULL,
            vote_type varchar(10) NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once ABSPATH . "wp-admin/includes/upgrade.php";
        dbDelta($sql);
    }
}
register_activation_hook(__FILE__, "create_voting_table");

// Create a custom table to store user votes.
function create_user_votes_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . "user_votes";

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9) NOT NULL,
            post_id mediumint(9) NOT NULL,
            vote_type varchar(10) NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once ABSPATH . "wp-admin/includes/upgrade.php";
        dbDelta($sql);
    }
}
register_activation_hook(__FILE__, "create_user_votes_table");

// Add the voting section below the posts.
function add_voting_section_to_single_post_content($content)
{
    if (is_single()) {
        $voting_section = '<div id="voting-section">';
        $voting_section .= '<p id="voting-text">Was this article helpful?</p>';
        $voting_section .=
            '<button id="vote-yes"><img class="smiles" src="' .
            plugin_dir_url(__FILE__) .
            'icons/smile-grey.svg" alt="Yes"><span id="yes-text">Yes</span></button>';
        $voting_section .=
            '<button id="vote-no"><img class="smiles" src="' .
            plugin_dir_url(__FILE__) .
            'icons/sad-grey.svg" alt="No"><span id="no-text">No</span></button>';
        $voting_section .= "</div>";
        $content .= $voting_section;
    }
    return $content;
}
add_filter("the_content", "add_voting_section_to_single_post_content");

// Check if the user has already voted on this post.
function has_user_voted($user_id, $post_id)
{
    global $wpdb;
    $table_name = $wpdb->prefix . "user_votes";
    $vote_exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND post_id = %d",
            $user_id,
            $post_id
        )
    );
    return $vote_exists > 0;
}

// Handle the vote and return voting results.
function vote_article()
{
    $post_id = $_POST["post_id"];
    $vote = $_POST["vote"];
    $current_user_id = get_current_user_id(); // Get the current user's ID.

    if ($current_user_id == 0) {
        $response = [
            "success" => false,
            "message" => "You must be logged in to vote.",
        ];
        wp_send_json($response);
    }

    if (has_user_voted($current_user_id, $post_id)) {
        $response = [
            "success" => false,
            "message" => "You have already voted on this post.",
        ];
        wp_send_json($response);
    }

    // Save the user's vote in the user_votes table
    global $wpdb;
    $table_name = $wpdb->prefix . "user_votes";
    $wpdb->insert(
        $table_name,
        [
            "user_id" => $current_user_id,
            "post_id" => $post_id,
            "vote_type" => $vote,
        ],
        ["%d", "%d", "%s"]
    );

    // Calculate the voting statistics
    $user_votes = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT vote_type FROM $table_name WHERE post_id = %d",
            $post_id
        )
    );

    $yes_votes = array_filter($user_votes, function ($user_vote) {
        return $user_vote->vote_type === "vote-yes";
    });

    $no_votes = array_filter($user_votes, function ($user_vote) {
        return $user_vote->vote_type === "vote-no";
    });

    $yes_percentage = (count($yes_votes) / count($user_votes)) * 100;
    $no_percentage = (count($no_votes) / count($user_votes)) * 100;

    $response = [
        "success" => true,
        "yes_percentage" => $yes_percentage,
        "no_percentage" => $no_percentage,
    ];
    wp_send_json($response);
}
add_action("wp_ajax_vote_article", "vote_article");
add_action("wp_ajax_nopriv_vote_article", "vote_article");

function localize_post_id()
{
    if (is_single()) {
        $post_id = get_the_ID();
        wp_localize_script("plugin-script", "voting_plugin_ajax", [
            "ajaxurl" => admin_url("admin-ajax.php"),
            "post_id" => $post_id, // Pass the post ID to JavaScript.
        ]);
    }
}
add_action("wp_enqueue_scripts", "localize_post_id");

add_action("wp_ajax_check_user_vote_status", "check_user_vote_status_callback");
add_action(
    "wp_ajax_nopriv_check_user_vote_status",
    "check_user_vote_status_callback"
);

function check_user_vote_status_callback()
{
    // Get the post ID and user ID
    $post_id = $_POST["post_id"];
    $current_user_id = get_current_user_id();

    // Check if the user has already voted
    global $wpdb;
    $table_name = $wpdb->prefix . "user_votes";
    $user_vote = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT vote_type FROM $table_name WHERE user_id = %d AND post_id = %d",
            $current_user_id,
            $post_id
        )
    );

    if ($user_vote) {
        $user_has_voted = true;
    } else {
        $user_has_voted = false;
    }

    // Calculate voting statistics
    $user_votes = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT vote_type FROM $table_name WHERE post_id = %d",
            $post_id
        )
    );

    $yes_votes = array_filter($user_votes, function ($user_vote) {
        return $user_vote->vote_type === "vote-yes";
    });

    $no_votes = array_filter($user_votes, function ($user_vote) {
        return $user_vote->vote_type === "vote-no";
    });

    $yes_percentage = (count($yes_votes) / count($user_votes)) * 100;
    $no_percentage = (count($no_votes) / count($user_votes)) * 100;

    $response = [
        "has_voted" => $user_has_voted,
        "user_vote" => $user_vote, // Include the user's vote
        "yes_percentage" => $yes_percentage,
        "no_percentage" => $no_percentage,
    ];

    wp_send_json($response);
}

// Add meta boxes to post meta in wp-admin
function add_voting_results_meta_box()
{
    add_meta_box(
        "voting_results_meta_box",
        "Voting Results",
        "display_voting_results_meta_box",
        "post",
        "side",
        "high"
    );
}
add_action("add_meta_boxes", "add_voting_results_meta_box");

// Display the voting results inside voting meta widget.
function display_voting_results_meta_box($post)
{
    $post_id = $post->ID;

    // Retrieve voting statistics from user_votes table
    global $wpdb;
    $table_name = $wpdb->prefix . "user_votes";
    $user_votes = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT vote_type FROM $table_name WHERE post_id = %d",
            $post_id
        )
    );

    $total_votes = count($user_votes);

    $yes_votes = array_filter($user_votes, function ($user_vote) {
        return $user_vote->vote_type === "vote-yes";
    });

    $no_votes = array_filter($user_votes, function ($user_vote) {
        return $user_vote->vote_type === "vote-no";
    });

    $yes_percentage =
        $total_votes > 0 ? (count($yes_votes) / $total_votes) * 100 : 0;
    $no_percentage =
        $total_votes > 0 ? (count($no_votes) / $total_votes) * 100 : 0;

    echo "<p><strong>Yes Votes:</strong> " .
        number_format($yes_percentage, 2) .
        "%</p>";
    echo "<p><strong>No Votes:</strong> " .
        number_format($no_percentage, 2) .
        "%</p>";
    echo "<p><strong>Total Votes:</strong> " . $total_votes . "</p>";
}
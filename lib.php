<?php
defined('MOODLE_INTERNAL') || die();

function console_log($output, $with_script_tags = true) {
    $js_code = 'console.log(' . json_encode($output, JSON_HEX_TAG) .
        ');';
    if ($with_script_tags) {
        $js_code = '<script>' . $js_code . '</script>';
    }
    echo $js_code;
}

// Callback function to include CSS file before the body tag
function filter_myfilter_before_standard_top_of_body_html() {
    global $PAGE, $CFG;

    // Add the CSS file to the page requirements manager
        $PAGE->requires->css('/filter/forumembed/styles.css');
}
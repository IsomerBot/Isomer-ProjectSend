<?php
if (!empty($search_form_action) || !empty($filters_form)) { ?>
        <div class="row">
            <div class="col-12">
                <div class="row">
                    <?php
                    if (!empty($search_form_action)) { ?>
                            <div class="col-12 col-md-6">
                                <?php show_search_form($search_form_action); ?>
                            </div>
                    <?php }

                    if (!empty($filters_form)) { ?>
                            <div class="col-12 col-md-6">
                                <div class="d-flex align-items-center">
                                    <?php show_filters_form($filters_form); ?>
                                    
                                    <?php if (
                                        isset($filters_form["clear_button"])
                                    ) {
                                        // Use the exact same styling as the Filter button: btn btn-md btn-pslight
                                        $clear_btn_class =
                                            "btn btn-md btn-pslight";

                                        // Add slightly different styling if no filters are active (optional)
                                        if (
                                            !$filters_form["clear_button"][
                                                "active"
                                            ]
                                        ) {
                                            // Keep same style but maybe add a subtle opacity difference
                                            $clear_btn_class =
                                                "btn btn-md btn-pslight";
                                        } else {
                                            $extra_style = "";
                                        }

                                        echo '<a href="' .
                                            $filters_form["clear_button"][
                                                "url"
                                            ] .
                                            '" class="' .
                                            $clear_btn_class .
                                            ' clear-filters-btn"' .
                                            $extra_style;

                                        // Add appropriate tooltips
                                        if (
                                            !$filters_form["clear_button"][
                                                "active"
                                            ]
                                        ) {
                                            echo ' title="No active filters to clear"';
                                        } else {
                                            echo ' title="Clear all active filters"';
                                        }

                                        echo ">";
                                        echo " Clear";
                                        echo "</a>";
                                    } ?>
                                </div>
                            </div>
                    <?php }
                    ?>
                </div>
            </div>
        </div>
<?php }

if (!empty($filters_links)) { ?>
        <div class="row">
            <div class="col-12">
                <div class="form_results_filter">
                    <?php foreach ($filters_links as $type => $filter) { ?>
                                <a href="<?php echo $filter[
                                    "link"
                                ]; ?>" class="<?php echo $search_type == $type
    ? "filter_current"
    : "filter_option"; ?>"><?php echo $filter["title"]; ?> (<?php echo $filter[
     "count"
 ]; ?>)</a>
                            <?php } ?>
                </div>
            </div>
        </div>
<?php }
?>

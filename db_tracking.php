<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * @package phpMyAdmin
 */

/**
 * Run common work
 */
require_once './libraries/common.inc.php';
require_once './libraries/Table.class.php';

require './libraries/db_common.inc.php';
$url_query .= '&amp;goto=tbl_tracking.php&amp;back=db_tracking.php';

// Get the database structure
$sub_part = '_structure';
require './libraries/db_info.inc.php';

// Work to do?
//  (here, do not use $_REQUEST['db] as it can be crafted)
if (isset($_REQUEST['delete_tracking']) && isset($_REQUEST['table'])) {
    PMA_Tracker::deleteTracking($GLOBALS['db'], $_REQUEST['table']);
}

// Get tracked data about the database
$data = PMA_Tracker::getTrackedData($_REQUEST['db'], '', '1');

// No tables present and no log exist
if ($num_tables == 0 && count($data['ddlog']) == 0) {
    echo '<p>' . __('No tables found in database.') . '</p>' . "\n";

    if (empty($db_is_information_schema)) {
        require './libraries/display_create_table.lib.php';
    }

    // Display the footer
    require_once './libraries/footer.inc.php';
    exit;
}

// ---------------------------------------------------------------------------

/*
 * Display top menu links
 */
require_once './libraries/db_links.inc.php';

// Prepare statement to get HEAD version
$all_tables_query = ' SELECT table_name, MAX(version) as version FROM ' .
             PMA_backquote($GLOBALS['cfg']['Server']['pmadb']) . '.' .
             PMA_backquote($GLOBALS['cfg']['Server']['tracking']) .
             ' WHERE ' . PMA_backquote('db_name')    . ' = \'' . PMA_sqlAddslashes($_REQUEST['db']) . '\' ' .
             ' GROUP BY '. PMA_backquote('table_name') .
             ' ORDER BY '. PMA_backquote('table_name') .' ASC';

$all_tables_result = PMA_query_as_controluser($all_tables_query);

// If a HEAD version exists
if (PMA_DBI_num_rows($all_tables_result) > 0) {
?>
    <h3><?php echo __('Tracked tables');?></h3>

    <table id="versions" class="data">
    <thead>
    <tr>
        <th><?php echo __('Database');?></th>
        <th><?php echo __('Table');?></th>
        <th><?php echo __('Last version');?></th>
        <th><?php echo __('Created');?></th>
        <th><?php echo __('Updated');?></th>
        <th><?php echo __('Status');?></th>
        <th><?php echo __('Action');?></th>
        <th><?php echo __('Show');?></th>
    </tr>
    </thead>
    <tbody>
    <?php

    // Print out information about versions

    $drop_image_or_text = '';
    if (true == $GLOBALS['cfg']['PropertiesIconic']) {
        $drop_image_or_text .= '<img class="icon" width="16" height="16" src="' . $pmaThemeImage . 'b_drop.png" alt="' . __('Delete tracking data for this table') . '" title="' . __('Delete tracking data for this table') . '" />';
    }
    if ('both' === $GLOBALS['cfg']['PropertiesIconic'] || false === $GLOBALS['cfg']['PropertiesIconic']) {
        $drop_image_or_text .= __('Drop');
    }

    $style = 'odd';
    while ($one_result = PMA_DBI_fetch_array($all_tables_result)) {
        list($table_name, $version_number) = $one_result;
        $table_query = ' SELECT * FROM ' .
             PMA_backquote($GLOBALS['cfg']['Server']['pmadb']) . '.' .
             PMA_backquote($GLOBALS['cfg']['Server']['tracking']) .
             ' WHERE `db_name` = \'' . PMA_sqlAddslashes($_REQUEST['db']) . '\' AND `table_name`  = \'' . PMA_sqlAddslashes($table_name) . '\' AND `version` = \'' . $version_number . '\'';

        $table_result = PMA_query_as_controluser($table_query);
        $version_data = PMA_DBI_fetch_array($table_result);

        if ($version_data['tracking_active'] == 1) {
            $version_status = __('active');
        } else {
            $version_status = __('not active');
        }
        $tmp_link = 'tbl_tracking.php?' . $url_query . '&amp;table=' . htmlspecialchars($version_data['table_name']);
        $delete_link = 'db_tracking.php?' . $url_query . '&amp;table=' . htmlspecialchars($version_data['table_name']) . '&amp;delete_tracking=true&amp';
        ?>
        <tr class="<?php echo $style;?>">
            <td><?php echo htmlspecialchars($version_data['db_name']);?></td>
            <td><?php echo htmlspecialchars($version_data['table_name']);?></td>
            <td><?php echo $version_data['version'];?></td>
            <td><?php echo $version_data['date_created'];?></td>
            <td><?php echo $version_data['date_updated'];?></td>
            <td><?php echo $version_status;?></td>
            <td><a href="<?php echo $delete_link;?>" onclick="return confirmLink(this, '<?php echo PMA_jsFormat(__('Delete tracking data for this table'), false); ?>')"><?php echo $drop_image_or_text; ?></a></td>
            <td> <a href="<?php echo $tmp_link; ?>"><?php echo __('Versions');?></a>
               | <a href="<?php echo $tmp_link; ?>&amp;report=true&amp;version=<?php echo $version_data['version'];?>"><?php echo __('Tracking report');?></a>
               | <a href="<?php echo $tmp_link; ?>&amp;snapshot=true&amp;version=<?php echo $version_data['version'];?>"><?php echo __('Structure snapshot');?></a></td>
        </tr>
        <?php
        if ($style == 'even') {
            $style = 'odd';
        } else {
            $style = 'even';
        }
    }
    unset($tmp_link);
    ?>
    </tbody>
    </table>
<?php
}

// Get list of tables
$table_list = PMA_getTableList($GLOBALS['db']);

// For each table try to get the tracking version
foreach ($table_list as $key => $value) {
    if (PMA_Tracker::getVersion($GLOBALS['db'], $value['Name']) == -1) {
        $my_tables[] = $value['Name'];
    }
}

// If untracked tables exist
if (isset($my_tables)) {
?>
    <h3><?php echo __('Untracked tables');?></h3>

    <table id="noversions" class="data">
    <thead>
    <tr>
        <th width="300"><?php echo __('Table');?></th>
        <th></th>
    </tr>
    </thead>
    <tbody>
<?php
    // Print out list of untracked tables

    $style = 'odd';

    foreach ($my_tables as $key => $tablename) {
        if (PMA_Tracker::getVersion($GLOBALS['db'], $tablename) == -1) {
            $my_link = '<a href="tbl_tracking.php?' . $url_query . '&amp;table=' . htmlspecialchars($tablename) .'">';

            if ($cfg['PropertiesIconic']) {
                $my_link .= '<img class="icon" src="' . $pmaThemeImage . 'eye.png" width="16" height="16" alt="' . __('Track table') . '" /> ';
            }
            $my_link .= __('Track table') . '</a>';
        ?>
            <tr class="<?php echo $style;?>">
            <td><?php echo htmlspecialchars($tablename);?></td>
            <td><?php echo $my_link;?></td>
            </tr>
        <?php
            if ($style == 'even') {
                $style = 'odd';
            } else {
                $style = 'even';
            }
        }
    }
    ?>
    </tbody>
    </table>

<?php
}
// If available print out database log
if (count($data['ddlog']) > 0) {
    $log = '';
    foreach ($data['ddlog'] as $entry) {
        $log .= '# ' . $entry['date'] . ' ' . $entry['username'] . "\n" . $entry['statement'] . "\n";
    }
    PMA_showMessage(__('Database Log'), $log);
}

/**
 * Display the footer
 */
require_once './libraries/footer.inc.php';
?>

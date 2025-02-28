<div class='ui-widget ui-widget-content ui-corner-all'>
    <div class='header ui-widget-header'><?php eT("Result");?></div>
    <div class='ui-widget ui-widget-content ui-corner-all row-fluid'>
    <div class="span8 offset2 ">
        <?php if (count($aResult['success'])) { ?>
            <div class='alert alert-success'><?php echo $lang['Success on :']; ?></div><ul class="">
            <?php foreach ($aResult['success'] as $string) { ?>
                <li><?php echo $string ?></li>
            <?php } ?>
            </ul>
        <?php } ?>
        <?php if (count($aResult['info'])) { ?>
            <div class='alert alert-info'><?php echo $lang['Information :']; ?></div><ul class="">
            <?php foreach ($aResult['info'] as $string) { ?>
                <li><?php echo $string ?></li>
            <?php } ?>
            </ul>
        <?php } ?>
        <?php if (count($aResult['warning'])) { ?>
            <div class='alert'><?php echo $lang['Warning on :']; ?></div><ul>
            <?php foreach ($aResult['warning'] as $string) { ?>
                <li><?php echo $string ?></li>
            <?php } ?>
            </ul>
        <?php } ?>
        <?php if (count($aResult['error'])) { ?>
            <div class='alert alert-error'><?php echo $lang['Error on :']; ?></div><ul>
            <?php foreach ($aResult['error'] as $string) { ?>
                <li><?php echo $string ?></li>
            <?php } ?>
            </ul>
        <?php } ?>
    </div>
    </div>
</div>

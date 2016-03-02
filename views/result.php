<div class='ui-widget ui-widget-content ui-corner-all'>
    <div class='header ui-widget-header'><?php eT("Result");?></div>
    <div class='ui-widget ui-widget-content ui-corner-all row-fluid'>
    <div class="span8 offset2 ">
        <?php if(count($aResult['success'])){ ?>
            <div class='alert alert-success'>Success on : </div><ul class="">
            <?php foreach($aResult['success'] as $string) { ?>
                <li><?php echo $string ?></li>
            <?php } ?>
            </ul>
        <?php } ?>
        <?php if(count($aResult['warning'])){ ?>
            <div class='alert'>Warning on : </div><ul>
            <?php foreach($aResult['warning'] as $string) { ?>
                <li><?php echo $string ?></li>
            <?php } ?>
            </ul>
        <?php } ?>
        <?php if(count($aResult['error'])){ ?>
            <div class='alert alert-error'>Error on :</div><ul>
            <?php foreach($aResult['error'] as $string) { ?>
                <li><?php echo $string ?></li>
            <?php } ?>
            </ul>
        <?php } ?>
    </div>
    </div>
</div>

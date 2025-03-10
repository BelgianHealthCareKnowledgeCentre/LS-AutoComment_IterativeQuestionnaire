<!-- RESULT LIST -->
<?php if (count($aResult['success'])) { ?>
    <div class=''><strong class="bg-success p-1 bg-opacity-75 d-block"><?php echo $lang['Success'] ?></strong>
    <ul class="">
    <?php foreach ($aResult['success'] as $string) { ?>
        <li><?php echo $string ?></li>
    <?php } ?>
    </ul>
    </div>
<?php } ?>
<?php if (count($aResult['warning'])) { ?>
    <div class=''><strong class="bg-warning p-1 bg-opacity-75 d-block"><?php echo $lang['Warning'] ?></strong><ul>
    <?php foreach ($aResult['warning'] as $string) { ?>
        <li><?php echo $string ?></li>
    <?php } ?>
    </ul></div>
<?php } ?>
<?php if (count($aResult['error'])) { ?>
    <div class=''><strong class="bg-danger p-1 d-block"><?php echo $lang['Error'] ?></strong><ul>
    <?php foreach ($aResult['error'] as $string) { ?>
        <li><?php echo $string ?></li>
    <?php } ?>
    </ul></div>
<?php } ?>

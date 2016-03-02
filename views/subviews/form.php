<!-- VALIDATE FORM -->
<?php echo CHtml::form($updateUrl, 'post', array('id'=>'validatekce', 'name'=>'validatekce', 'class'=>'form30', 'enctype'=>'multipart/form-data')); ?>
<?php
#echo "<pre>".var_export($aSettings,1)."</pre>";
    $this->widget('ext.SettingsWidget.SettingsWidget', array(
        'settings' => $aSettings,
        'method' => 'post',
        'form' => false,
        'buttons' => array(
                gT('Confirm') => array(
                    'name' => 'confirm',
                    'type'=> 'submit',
                    'htmlOptions'=>array(
                        'value'=>'confirm',
                    ),
                ),
            gT('Cancel') => array(
                'name' => 'cancel'
            ),
        )
    ));
    ?>
<?php echo CHtml::endForm(); ?>


<!-- VALIDATE FORM -->
<?php echo CHtml::form($updateUrl, 'post', array('id' => 'validatekce', 'name' => 'validatekce', 'class' => 'form-horizontal', 'enctype' => 'multipart/form-data')); ?>
<?php
foreach ($aSettings as $legend => $aSetting) {
    $this->widget('ext.SettingsWidget.SettingsWidget', array(
        //'id'=>'summary',
        'title' => $legend,
        //'prefix' => $pluginClass, This break the label (id!=name)
        'form' => false,
        'formHtmlOptions' => array(
            'class' => 'form-core',
        ),
        'labelWidth' => 6,
        'controlWidth' => 6,
        'settings' => $aSetting,
    ));
}
?>
        <div class='row'>
          <div class='col-md-offset-6 submit-buttons'>
            <?php
              echo CHtml::htmlButton('<i class="fa fa-check" aria-hidden="true"></i> ' . $buttons['confirm'], array('type' => 'submit','name' => 'confirm','value' => 'confirm','class' => 'btn btn-primary'));
              echo " ";
              //~ echo CHtml::htmlButton('<i class="fa fa-check-circle-o " aria-hidden="true"></i> '.gT('Save and close'),array('type'=>'submit','name'=>'save'.$pluginClass,'value'=>'redirect','class'=>'btn btn-default'));
              //~ echo " ";
              echo CHtml::link($buttons['cancel'], Yii::app()->createUrl('admin/survey', array('sa' => 'view','surveyid' => $surveyid)), array('class' => 'btn btn-danger'));
            ?>
            <div class='hidden' style='display:none'>
              <div data-moveto='surveybarid' class='pull-right hidden-xs'>
              <?php
                echo CHtml::link('<i class="fa fa-check" aria-hidden="true"></i> ' . $buttons['confirm'], "#", array('class' => 'btn btn-primary','data-click-name' => 'confirm','data-click-value' => 'confirm'));
                echo " ";
              //~ echo CHtml::link('<i class="fa fa-check-circle-o" aria-hidden="true"></i> '.gT('Save and close'),"#",array('class'=>'btn btn-default','data-click-name'=>'save'.$pluginClass,'data-click-value'=>'redirect'));
              //~ echo " ";
                echo CHtml::link($buttons['cancel'], Yii::app()->createUrl('admin/survey', array('sa' => 'view','surveyid' => $surveyid)), array('class' => 'btn btn-danger'));
                ?>
              </div>
            </div>
          </div>
        </div>
<?php echo CHtml::endForm(); ?>

<div class="row">
    <div class="col-lg-12 content-right">
        <h3><?php echo $title ?></h3>
        <?php echo CHtml::form($updateUrl, 'post', array('id' => 'validatekce', 'name' => 'validatekce', 'class' => 'form-horizontal', 'enctype' => 'multipart/form-data')); ?>
        <?php $this->widget('ext.SettingsWidget.SettingsWidget', array(
                'formHtmlOptions' => array(
                    'class' => 'form-core',
                ),
                'labelWidth' => 6,
                'controlWidth' => 6,
                'settings' => $settings,
                'form' => false,
            )); ?>
            <div class='row'>
              <div class='col-md-6'></div>
              <div class='col-md-6 submit-buttons'>
                <?php
                if ($buttons['validate']) {
                    echo CHtml::htmlButton('<i class="fa fa-check" aria-hidden="true"></i> ' . $buttons['validate'], array('type' => 'submit','name' => 'confirm','value' => 'confirm','class' => 'btn btn-primary'));
                    echo " ";
                }
                  echo CHtml::link($buttons['cancel'], Yii::app()->createUrl('surveyAdministration/view', array('surveyid' => $surveyid)), array('class' => 'btn btn-danger'));
                ?>
              </div>
            </div>
        </form>
    </div>
</div>

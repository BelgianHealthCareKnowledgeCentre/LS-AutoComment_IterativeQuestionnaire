<div class='ui-widget ui-widget-content ui-corner-all'>
    <div class='header ui-widget-header'><?php $clang->eT("Survey selection");?></div>
    <?php echo CHtml::form($updateUrl, 'post', array('id'=>'validatekce', 'name'=>'validatekce', 'class'=>'form30', 'enctype'=>'multipart/form-data')); ?>
    <?php $this->widget('ext.SettingsWidget.SettingsWidget', array(
            'settings' => $settings,
            'form' => false,
            'buttons' => array(
                gT('Validate question before import') => array(
                    'name' => 'confirm'
                ),
                gT('Cancel') => array(
                    'name' => 'cancel'
                ),
            )
        )); ?>
    </form>
</div>

<div class='ui-widget ui-widget-content ui-corner-all'>
    <div class='header ui-widget-header'><?php eT("Survey selection");?></div>
    <?php echo CHtml::form($updateUrl, 'post', array('id'=>'validatekce', 'name'=>'validatekce', 'class'=>'form30', 'enctype'=>'multipart/form-data')); ?>
    <?php $this->widget('ext.SettingsWidget.SettingsWidget', array(
            'settings' => $settings,
            'form' => false,
            'buttons' => $buttons,
        )); ?>
    </form>
</div>

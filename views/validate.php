<div class="row">
    <h3><?php echo $title ?></h3>
    <?php
    if($bSurveyActivated) {
        echo CHtml::tag('div',array('class'=>'alert alert-danger'),$lang["This survey is activated. You can not create question."]);
    }
    if(!count($aSettings)) {
        echo CHtml::tag('div',array('class'=>'alert alert-danger'),$lang["No Delphi questions found. Are you sure to activate %s and set some value different of 0 for some question."]);
    }
    ?>
    <?php 
    include "subviews/result.php";
    include "subviews/form.php";
    ?>
</div>

<div class="row">
    <h3><?php echo $title ?></h3>
    <?php if($bSurveyActivated) { ?>
        <div class='alert alert-danger'>This survey is activated. You can not create question</div>
    <?php } ?>
    <?php if(!count($aSettings)) { ?>
        <div class='alert alert-danger'>No Delphi questions found. Are you sure to activate <a href='//manual.limesurvey.org/Assessments' rel='external' title='LimeSurvey manual'>assessment</a> and set some value different of 0 for some question.</div>
    <?php } ?>
    <?php include "subviews/result.php" ?>

    <?php include "subviews/form.php" ?>

</div>

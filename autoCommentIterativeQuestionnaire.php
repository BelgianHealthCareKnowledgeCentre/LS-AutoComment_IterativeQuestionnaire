<?php
/**
 * autoCommentIterativeQuestionnaire Plugin for LimeSurvey
 * Creates automatic comment questions, and for iterative quesitonnaires, create a new questionnaire from a previous round questionnaire
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2014-2025 Denis Chenu <http://sondages.pro>
 * @copyright 2014-2025 Belgian Health Care Knowledge Centre (KCE) <http://kce.fgov.be>
 * @license AGPL v3
 * @version 4.2.3
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 */
class autoCommentIterativeQuestionnaire extends PluginBase {

    protected $storage = 'DbStorage';
    static protected $name = 'autoCommentIterativeQuestionnaire';
    static protected $description = 'Creates automatic comment questions, and for iterative quesitonnaires, create a new questionnaire from a previous round questionnaire - v3.0';

    /** @inheritdoc */
    public $allowedPublicMethods = array(
        'actionCheck',
        'actionSelect',
        'actionUpdate',
        'actionValidate',
    );

    /* @var string language to be used */
    private $language = "";

    private $iSurveyId=false;
    private $bSurveyActivated=false;
    private $sTableName="";
    private $sError="Unknow error.";
    
    private $bUpdateHistory=false;
    private static $aValidQuestion=array("!","L","O");
    private static $aTextQuestion=array("S","T","U");

    private $oldSchema;
    private $aResult=array('success'=>array(),'warning'=>array(),'error'=>array());
    //~ private $validatescore,$scoreforyes,$scoreforno;

    private $aDelphiCodes=array(
        'hist'=>array(
            'questiontype'=>"X",
            'select'=>array(
                'label'=>"Display the question (text) from the previous round",
                'options'=>array(
                    'none' => 'No, do not create it',
                    'hide' => 'No, do not display it',
                    'create' => "Yes, create and display it",
                    'update' => "Yes, display it",
                    "show" => "Yes, display it, but don’t update",
                ),
            ),
        ),
        'comm'=>array(
            'questiontype'=>"T",
            'create'=>  false,
            'select'=>array(
                    'label'=>"Comment question (automatic)",
                    'options'=>array(
                        'none'=>'No creation of this question',
                        'create'=>"Create question with default text (and Show it)",
                    ),
                ),
            'condition'=>"{QCODE}.valueNAOK < 0",
            'hidevalidate'=>false,
        ),
    );
    private $aLang=array(
        'hist'=>array(
            'view'=>"Show the history question",
            'createupdate'=>"Create an history question with actual question text",
            'update'=>"Update history with <strong>actual</strong> question text",
            ),
        'comm'=>array(
            'view'=>"Have a question for comment (Disagree)",
            'create'=>"Create a question to enter comment (automatic :assesment of question is under 0)",
            'createupdate'=>"Create a question to enter comment (when disagree : with condition : assesment of question is under 0 (score))",
            'update'=>"",
            ),
        'comh'=>array(
            'view'=>"Shown the comment for reason of disagree list",
            'create'=>"Create an empty question for reason of disagree list",
            'createupdate'=>"Create and update a question for reason of disagree list (only if question have comment)",
            'update'=>"Update the reason of disagree list",
            ),
        'cgd'=>array(
            'view'=>"Have a question for alternative comment (Agree)",
            'create'=>"Create a question to enter alternative comment (agree : with condition : assesment of question is upper 0 (score))",
            'createupdate'=>"Create a question to enter alternative comment (agree : with condition : assesment of question is upper 0 (score))",
            'update'=>"",
            ),
        'cgdh'=>array(
            'view'=>"Shown the comment for reason of agree list",
            'create'=>"Create an empty question for reason of agree list",
            'createupdate'=>"Create and update a question for reason of agree list (only if question have comment)",
            'update'=>"Update the reason of agree list",
            ),
        );

    /** @inheritdoc */
    public function init() {
        if (Yii::app() instanceof CConsoleApplication) {
            return;
        }
        /* Basic settings */
        $this->subscribe('beforeSurveySettings');
        $this->subscribe('newSurveySettings');

        /* Add the link in menu */
        $this->subscribe('beforeToolsMenuRender');
        // Add the Question attribute for managing help to content
        $this->subscribe('newQuestionAttributes');
        $this->subscribe('beforeQuestionRender');

        // Update own var for language
        $this->_updateLanguageString();
    }

    /* Update language string in var */
    private function _updateLanguageString()
    {
        $this->sError = $this->gT("Unknow error.");
        $this->aDelphiCodes=array_merge($this->aDelphiCodes, array(
            'hist'=>array(
                'select'=>array(
                    'label'=>$this->gT("Display the question (text) from the previous round"),
                    'options'=>array(
                        'none'=>$this->gT("No, do not display it"),
                        'hide'=>$this->gT("No, do not display it"),
                        'create'=>$this->gT("Yes, display it"),
                        'update'=>$this->gT("Yes, display it"),
                        'show'=>$this->gT("Yes, display it, but don’t update"),
                    ),
                ),
            ),
            'comm'=>array(
                'select'=>array(
                    'label'=>$this->gT("Comment question (automatic)"),
                    'options'=>array(
                        'none'=>$this->gT("No creation of this question"),
                        'create'=>$this->gT("Create question with default text (and Show it)"),
                    ),
                ),
            ),
        ));
        $this->aLang=array(
            'hist'=>array(
                'view'=>$this->gT("Show the history question"),
                'createupdate'=>$this->gT("Create an history question with actual question text"),
                'update'=>$this->gT("Update history with current question text"),
            ),
            'comm'=>array(
                'view'=>$this->gT("Have a question for comment (Disagree)"),
                'create'=>$this->gT("Create a question to enter comment (automatic :assesment of question is under 0)"),
                'createupdate'=>$this->gT("Create a question to enter comment (when disagree : with condition : assesment of question is under 0 (score))"),
                'update'=>"",
            ),
            'comh'=>array(
                'view'=>$this->gT("Shown the comment for reason of disagree list"),
                'create'=>$this->gT("Create an empty question for reason of disagree list"),
                'createupdate'=>$this->gT("Create and update a question for reason of disagree list (only if question have comment)"),
                'update'=>$this->gT("Update the reason of disagree list"),
            ),
            'cgd'=>array(
                'view'=>$this->gT("Have a question for alternative comment (Agree)"),
                'create'=>$this->gT("Create a question to enter alternative comment (agree : with condition : assesment of question is upper 0 (score))"),
                'createupdate'=>$this->gT("Create a question to enter alternative comment (agree : with condition : assesment of question is upper 0 (score))"),
                'update'=>"",
            ),
            'cgdh'=>array(
                'view'=>$this->gT("Shown the comment for reason of agree list"),
                'create'=>$this->gT("Create an empty question for reason of agree list"),
                'createupdate'=>$this->gT("Create and update a question for reason of agree list (only if question have comment)"),
                'update'=>$this->gT("Update the reason of agree list"),
            ),
        );
    }
    /** @inheritdoc */
    public function newQuestionAttributes() {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        $questionAttributes=array(
            'iterativeQuestion'=> array(
                'types' => "X",
                'category' => gT('Display'),
                'sortorder' => 200,
                'inputtype'=>'switch',
                'options'=>array(
                    0=>gT('No'),
                    1=>gT('Yes')
                ),
                'default'=>0,
                'caption' => $this->gT("Show help as history"),
                'help' => $this->gT("If you use iterative questionaire, this question help are shown as history. Automatically set by iterative questionaire."),
            ),
        );
        $this->getEvent()->append('questionAttributes', $questionAttributes);
    }

    /** @inheritdoc */
    public function beforeQuestionRender() {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        if($this->getEvent()->get('type') != "X") {
            return;
        }
        $oEvent = $this->getEvent();
        $isIterativeQuestion = QuestionAttribute::model()->find(
            "qid = :qid and attribute = :attribute",
            array(":qid"=>$oEvent->get('qid'),":attribute" => 'iterativeQuestion')
        );
        if(empty($isIterativeQuestion) || $isIterativeQuestion->value == 0) {
            return;
        }
        $historyId = "ciq-history-".$oEvent->get('qid');
        $questionText = $oEvent->get('text');
        $questionHelp = $oEvent->get('help');
        $questionClass = $oEvent->get('class');
        $oEvent->set('class',$oEvent->get('class').' aciq-history');
        $oEvent->set('help',"");
        $this->subscribe('getPluginTwigPath', 'twigQuestionText');
        $newText = Yii::app()->twigRenderer->renderPartial(
            '/subviews/survey/question_subviews/question_text_iterativequestion.twig',
            array(
                'historyId' => $historyId,
                'questionText' => $questionText,
                'historyText' => $questionHelp,
                'questionClass' => $questionClass
            )
        );
        $oEvent->set('text',$newText);
        if(!Yii::app()->clientScript->hasPackage('autoCommentIterativeQuestionnaire')) {
            Yii::setPathOfAlias('autoCommentIterativeQuestionnaire',dirname(__FILE__));
            Yii::app()->clientScript->addPackage( 'autoCommentIterativeQuestionnaire', array(
                'basePath'    => 'autoCommentIterativeQuestionnaire.assets',
                'css'         => array('autoCommentIterativeQuestionnaire.css'),
                //~ 'js'          => array('questionExtraSurvey.js'),
            ));
            Yii::app()->getClientScript()->registerPackage('autoCommentIterativeQuestionnaire');
        }
    }

    /** append directory to twig */
    public function twigQuestionText()
    {
        $viewPath = dirname(__FILE__) . "/twig";
        $this->getEvent()->append('add', array($viewPath));
    }

    /** Menu and settings part */

    /** @inheritdoc */
    public function beforeToolsMenuRender()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        $event = $this->getEvent();
        $surveyId = $event->get('surveyId');
        if(!Permission::model()->hasSurveyPermission($surveyId, 'surveycontent', 'update')) {
            return;
        }
        $menuItems = array();
        // Did we have an old survey ?
        $aTables = App()->getApi()->getOldResponseTables($surveyId);
        if(count($aTables)>0) {
            $aMenuItem = array(
                'label' => $this->gT('Iteration'),
                'iconClass' => 'fa fa-refresh',
                'href' => Yii::app()->createUrl(
                    'admin/pluginhelper',
                    array(
                        'sa' => 'sidebody',
                        //~ 'href' => $url,
                        'plugin' => get_class($this),
                        'method' => 'actionSelect',
                        'surveyId' => $surveyId
                    )
                ),
            );
            if (class_exists("\LimeSurvey\Menu\MenuItem")) {
                $menuItems[] = new \LimeSurvey\Menu\MenuItem($aMenuItem);
            } else {
                $menuItems[] = new \ls\menu\MenuItem($aMenuItem);
            }
        }
        if(empty($aTables)) {
            $aMenuItem = array(
                'label' => $this->gT('Check survey for iteration'),
                'iconClass' => 'fa fa-refresh',
                'href' => Yii::app()->createUrl(
                    'admin/pluginhelper',
                    array(
                        'sa' => 'sidebody',
                        //~ 'href' => $url,
                        'plugin' => get_class($this),
                        'method' => 'actionCheck',
                        'surveyId' => $surveyId
                    )
                ),
            );
            if (class_exists("\LimeSurvey\Menu\MenuItem")) {
                $menuItems[] = new \LimeSurvey\Menu\MenuItem($aMenuItem);
            } else {
                $menuItems[] = new \ls\menu\MenuItem($aMenuItem);
            }
        }
        $event->append('menuItems', $menuItems);
    }

    /** @inheritdoc */
    public function beforeSurveySettings()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        $oEvent = $this->event;
        $iSurveyId=$oEvent->get('survey');
        $oSurvey=Survey::model()->findByPk($iSurveyId);
        if(
          $oSurvey && $oSurvey->assessments=='Y'
          && Permission::model()->hasSurveyPermission($iSurveyId, 'surveycontent', 'update')
        )
        {
            $aSettings=array();

            // Setting for language
            $oSurvey=Survey::model()->findByPk($iSurveyId);
            $aLangs=$oSurvey->getAllLanguages();
            //$sScoreDefault=$this->get('validatescore', 'Survey', $oEvent->get('survey'),$this->get('validatescore',null,null,$this->settings['validatescore']['default']));
            foreach($aLangs as $sLang)
            {
                $aSettings["historytext_{$sLang}"]=array(
                    'type'=>'string',
                    'label'=>sprintf($this->gT("Sentence added before old proposal history (%s)"),$sLang),
                    'htmlOptions' => array(
                        'placeholder' => $this->gT('Previous proposal and results','html',$sLang)
                    ),
                    'current' => $this->get("historytext_{$sLang}", 'Survey', $oEvent->get('survey'),""),
                );
            }
            foreach($aLangs as $sLang)
            {
                $aSettings["commenttext_{$sLang}"]=array(
                    'type'=>'string',
                    'label'=>sprintf($this->gT("Sentence for the comments question show if user choose a answer with value less than 0 (%s)"),$sLang),
                    'htmlOptions' => array(
                        'placeholder' => $this->gT('Please explain why.','html',$sLang)
                    ),
                    'current' => $this->get("commenttext_{$sLang}", 'Survey', ""),
                );
            }

            foreach($aLangs as $sLang)
            {
                $aSettings["commenthist_{$sLang}"]=array(
                    'type'=>'string',
                    'label'=>"Sentence added before comment list ({$sLang})",
                    'htmlOptions' => array(
                        'placeholder' => $this->gT('Previous comment(s).','html',$sLang)
                    ),
                    'current' => $this->get("commenthist_{$sLang}", 'Survey', $oEvent->get('survey'),""),
                );
            }

            // Default history
            $aSettings["updatequestion"]=array(
                'type'=>'select',
                'label'=>"Update and create history question by default.",
                'class'=>' delphiinput',
                'options' => array(
                  'Y'=>gt("Yes"),
                  'N'=>gt("No"),
                  ),
                'current' => $this->get("updatequestion", 'Survey', $oEvent->get('survey'),"Y"),
            );
            // Did we have an old survey ?
            $aTables = App()->getApi()->getOldResponseTables($iSurveyId);
            if(count($aTables)>0) {
                $aSettings['launch']=array(
                    'type'=>'link',
                    'link'=>Yii::app()->createUrl('admin/pluginhelper',array('plugin' => get_class($this),'sa'=>'sidebody','method' => 'actionSelect','surveyId' => $iSurveyId)),
                    'label'=>'Update the survey according to an old answer table',
                    'help'=>'Attention, you lost actual settings',
                    'class'=>array('btn-link','delphi-link'),
                );
            } else {
                $aSettings['check']=array(
                    'type'=>'link',
                    'link'=>Yii::app()->createUrl('admin/pluginhelper',array('plugin' => get_class($this),'sa'=>'sidebody','method' => 'actionCheck','surveyId' => $iSurveyId,'function' => 'check')),
                    'label'=>'Update the survey to add needed question',
                    'help'=>'Attention, you lost actual settings',
                    'class'=>array('btn-link','delphi-link'),
                );
            }

            // Add the string for each language
            $oEvent->set("surveysettings.{$this->id}", array('name' => get_class($this),'settings'=>$aSettings));
        }
        elseif( $oSurvey && $oSurvey->assessments!='Y' )
        {
            $aSettings['info']=array(
                'type'=>'info',
                'content'=>"<div class='alert'><strong>".gT("Assessments mode not activated")."</strong> <br />".sprintf(gT("Assessment mode for this survey is not activated. You can activate it in the %s survey settings %s (tab 'Notification & data management')."),'<a href="'.Yii::app()->createUrl('admin/assessments/',array('sa'=>'index','surveyid'=>$iSurveyId)).'">','</a>')."</div>",
            );
            $oEvent->set("surveysettings.{$this->id}", array('name' => get_class($this),'settings'=>$aSettings));
        }
        $assetUrl = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets');
    }
    public function newSurveySettings()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        $event = $this->event;
        foreach ($event->get('settings') as $name => $value) {
            $default=$event->get($name,null,null,isset($this->settings[$name]['default']) ? $this->settings[$name]['default'] : NULL);
            $this->set($name, $value, 'Survey', $event->get('survey'),$default);
        }
    }

    public function setBaseLanguage()
    {
        if (!$this->iSurveyId) {
            throw new CHttpException(403);
        }
        $oSurvey = Survey::model()->findByPk($this->iSurveyId);
        $aAllLanguage = $oSurvey->getAllLanguages();
        if (in_array(App()->session['adminlang'], $aAllLanguage)) {
            $this->language = App()->session['adminlang'];
        } else {
            $this->language = $oSurvey->language;
        }
    }

    public function actionCheck()
    {
        $this->iSurveyId = $this->api->getRequest()->getParam('surveyId');
        $this->checkAccess();
        if (!$this->checkCompatibilityAdmin()) {
            $aData = array(
                'title' => $this->gT("Unable to manage"),
                'compatibilitydetail' => $this->gT("This plugin can be used for public part but you can not manage settings."),
            );
            return $this->_renderPartial(
                $aData,
                array("compatibility")
            );
        }
        $this->setBaseLanguage();
        $oRequest = $this->api->getRequest();
        if($oRequest->getPost('cancel')) {
            App()->controller->redirect(
                array(
                    'admin/survey',
                    'sa' => 'view',
                    'surveyid' => $this->iSurveyId
                )
            );
        }
        if ($oRequest->getIsPostRequest() && $oRequest->getPost('confirm')) {
            $aQuestionsValidations = $oRequest->getPost('q', array());
            foreach($aQuestionsValidations as $iQid => $aQuestionValidations) {
                foreach($aQuestionValidations as $sType=>$aQuestionActions) {
                    foreach($aQuestionActions as $sAction=>$sDo) {
                        if($sDo) {
                            $this->doQuestion($iQid, $sType, $sAction);
                        }
                    }
                }
            }
            Yii::app()->setFlashMessage($this->gT("Survey updated"));
        }
        $oQuestions = $this->getDelphiQuestion();
        $aQuestionsSettings = array();
        $aQuestionsInfo = array();
        $aSettings = array();
        foreach($oQuestions as $oQuestion) {
            $aQuestionSetting = array();
            $sFieldName=$oQuestion->sid."X".$oQuestion->gid."X".$oQuestion->qid;
            $aQuestionsInfo[$oQuestion->qid]=array();
            // Test if question is hidden (already validated)
            $oAttributeHidden=QuestionAttribute::model()->find("qid=:qid AND attribute='hidden'",array(":qid"=>$oQuestion->qid));
            $sQuestionTextTitle=FlattenText($oQuestion->question);
            $sQuestionText=ellipsize($sQuestionTextTitle,80);
            if($oQuestion->title!==preg_replace("/[^_a-zA-Z0-9]/", "", $oQuestion->title)) {
                $aQuestionSetting["q_{$oQuestion->qid}"]['type']='info';
                $aQuestionSetting["q_{$oQuestion->qid}"]['content']=CHtml::tag('div',array('class'=>'questiontitle','title'=>$sQuestionTextTitle),"<strong>Invalid title : {$oQuestion->title}</strong> : LimeSurvey 2.05 title allow only alphanumeric (no space, no dot ..)");
            } elseif($oAttributeHidden && $oAttributeHidden->value) {
                $aQuestionSetting["q_{$oQuestion->qid}"]['type']='info';
                $aQuestionSetting["q_{$oQuestion->qid}"]['content']=CHtml::tag('div',array('class'=>'questiontitle','title'=>$sQuestionTextTitle),"<strong>Validated question {$oQuestion->title}</strong> : {$sQuestionText}");
            } else {
                foreach($this->aDelphiCodes as $sDelphiCode=>$aSettings) {
                    $aQuestionSetting=array_merge($aQuestionSetting,$this->getCheckQuestionSettings($oQuestion->qid,$oQuestion->title,$sDelphiCode));
                }
            }
            $aQuestionsSettings["<strong>{$oQuestion->title}</strong> : {$sQuestionText}"] = $aQuestionSetting;
        }
        $aData['aSettings']=$aQuestionsSettings;
        $aData['title']=$this->gT("Check survey");
        $aData['aResult']=$this->aResult;
        $aData['buttons'] = array(
            'confirm' => $this->gT("Confirm"),
            'cancel' => $this->gT("Cancel"),
        );
        $aData['updateUrl']=Yii::app()->createUrl('admin/pluginhelper', array('sa'=>'sidebody','plugin' => get_class($this), 'method' => 'actionCheck','surveyId'=>$this->iSurveyId));
        return $this->_renderPartial($aData,array("validate"));
    }
    /**
    * Show the form
    *
    **/
    public function actionSelect()
    {
        $this->iSurveyId = $this->api->getRequest()->getParam('surveyId');
        $this->checkAccess();
        if (!$this->checkCompatibilityAdmin()) {
            $aData = array(
                'title' => $this->gT("Unable to manage"),
                'compatibilitydetail' => $this->gT("This plugin can be used for public part but you can not manage settings."),
            );
            return $this->_renderPartial(
                $aData,
                array("compatibility")
            );
        }
        //$baseSchema = SurveyDynamic::model($this->iSurveyId)->getTableSchema();
        $aTables = App()->getApi()->getOldResponseTables($this->iSurveyId);
        if(count($aTables)) {
            rsort ($aTables);
        }
        $list=array();
        foreach ($aTables as $table)
        {
            $criteria= New CDbCriteria;
            $criteria->condition="submitdate IS NOT NULL";
            $count = PluginDynamic::model($table)->count($criteria);
            $timestamp = date_format(new DateTime(substr($table, -14)), 'Y-m-d H:i:s');
            if($count>0)
                $list[$table]  = "$timestamp ($count responses)";
        }
        if(!empty($list))
        {
            $aData['settings']['oldsurveytable'] = array(
                'label' => gT('Source table'),
                'type' => 'select',
                'options' => $list,
                'htmlOptions' => array(
                    'empty'=>gT("Please choose …"),
                    'required'=>true,
                ),
            );
            $aData['settings']['withuncompleted'] = array(
                'label' => gT('Not completed'),
                'type' => 'checkbox',
            );
            $aData['buttons'] = array(
                'validate'=> $this->gT('Validate question before import'),
                'cancel' => gT('Cancel'),
            );
        }else{
            $aData['settings']['oldsurveytable'] = array(
                'type' => 'info',
                'content' => CHtml::tag('div',array('class'=>'alert'),'You have old survey table, but no answer inside'),
            );
            $aData['buttons'] = array(
                'validate'=> null,
                'cancel' => $this->gT('Cancel'),
            );
        }
        $aData['title'] = $this->gT("Survey selection");
        $aData['updateUrl']=Yii::app()->createUrl('admin/pluginhelper', array('sa'=>'sidebody','plugin' => get_class($this), 'method' => 'actionValidate','surveyId'=>$this->iSurveyId));
        return $this->_renderPartial($aData,array("select"));
    }
    /**
    * Validate survey before updating
    *
    **/
    public function actionValidate()
    {
        $this->iSurveyId = $this->api->getRequest()->getParam('surveyId');
        $this->checkAccess();
        if (!$this->checkCompatibilityAdmin()) {
            $aData = array(
                'title' => $this->gT("Unable to manage"),
                'compatibilitydetail' => $this->gT("This plugin can be used for public part but you can not manage settings."),
            );
            return $this->_renderPartial(
                $aData,
                array("compatibility")
            );
        }
        $this->setBaseLanguage();
        if(Yii::app()->request->getPost('oldsurveytable')) {
            $this->bUpdateHistory=($this->get("updatequestion", 'Survey', $this->iSurveyId,"Y")=="Y");
            $sTableName=$this->sTableName=Yii::app()->request->getPost('oldsurveytable');
            $aTables = App()->getApi()->getOldResponseTables($this->iSurveyId);
            if(!in_array($sTableName,$aTables)){
                Yii::app()->setFlashMessage($this->gT("Bad table name."),'error');
                App()->controller->redirect(
                    Yii::app()->createUrl('admin/pluginhelper',
                        array('plugin' => get_class($this),'sa'=>'sidebody','surveyId'=>$this->iSurveyId, 'methode' => 'actionSelect')
                    )
                );
            }
            if(Yii::app()->request->getPost('confirm')) {
                $this->actionUpdate();
            }
            $oldTable = PluginDynamic::model($sTableName);
            $this->oldSchema = $oldSchema = $oldTable->getTableSchema();

            // Ok get the information ...
            $oQuestions=$this->getAllQuestion();
            $aQuestions=array();
            $aQuestionsSettings=array();
            $aQuestionsInfo=array();

            foreach($oQuestions as $oQuestion){
                $legend = $aQuestionsSetting = null;
                if(in_array($oQuestion->type,self::$aValidQuestion)) {
                    $aQuestionsSetting=$this->getValidateQuestionSettings($oQuestion);
                    $legend = "<span class='label label-info'>{$oQuestion->title}</span> : ".ellipsize(flattenText($oQuestion->question),50);
                } elseif(in_array($oQuestion->type,self::$aTextQuestion)) {
                    $aQuestionsSetting=$this->getCommentQuestionSettings($oQuestion);
                    $legend = "<span class='label label-info'>{$oQuestion->title}</span> : ".ellipsize(flattenText($oQuestion->question),50);
                }
                if($legend){
                    $aQuestionsSettings[$legend] = $aQuestionsSetting;
                }
            }
            $aSurveySettings[$this->gT("Previous responses")]['oldsurveytable']=array(
                'label' => gT('Source table'),
                'type' => 'select',
                'options' => array($sTableName=>$sTableName),
                'current' => $sTableName,
                'htmlOptions'=>array(
                    'readonly'=>true,
                ),
            );
            $aSurveySettings[$this->gT("Previous responses")]['withuncompleted'] = array(
                'label' => gT('Not completed'),
                'type' => 'checkbox',
                'current' => App()->request->getPost('withuncompleted'),
            );
            $aData['aSettings']=array_merge($aQuestionsSettings,$aSurveySettings);
            $aData['buttons'] = array(
                'confirm' => $this->gT("Confirm update of survey"),
                'cancel' => $this->gT("Cancel"),
            );
            $aData['updateUrl']=Yii::app()->createUrl('admin/pluginhelper', array('sa'=>'sidebody','plugin' => get_class($this), 'method' => 'actionValidate','surveyId'=>$this->iSurveyId));
            LimeExpressionManager::SetDirtyFlag();
            return $this->_renderPartial($aData,array("validate"));
        } else {
            App()->controller->redirect(array('/admin/survey/sa/view','surveyid'=>$this->iSurveyId));
        }
    }
    /**
    * Update survey with old survey
    *
    **/
    public function actionUpdate()
    {
        $this->iSurveyId = $this->api->getRequest()->getParam('surveyId');
        $this->checkAccess();
        $this->setBaseLanguage();
        $oRequest = $this->api->getRequest();
        if($oRequest->getPost('cancel')) {
            App()->controller->redirect(array('admin/survey','sa'=>'view','surveyid'=>$this->iSurveyId));
        }
        $oldSchema = null;
        if($oRequest->getIsPostRequest() && $oRequest->getPost('confirm'))
        {
            if(!$this->oldSchema) {
                $sTableName=$this->sTableName=Yii::app()->request->getPost('oldsurveytable');
                    $aTables = App()->getApi()->getOldResponseTables($this->iSurveyId);
                    if(!in_array($sTableName,$aTables)){
                        Yii::app()->setFlashMessage("Bad table name.",'error');
                        App()->controller->redirect(
                            Yii::app()->createUrl('admin/pluginhelper',
                                array('plugin' => get_class($this),'sa'=>'sidebody','surveyId'=>$this->iSurveyId, 'methode' => 'actionSelect')
                            )
                        );
                    }
                    $oldTable = PluginDynamic::model($sTableName);
                    $this->oldSchema = $oldSchema = $oldTable->getTableSchema();
            }else {
                $oldSchema=$this->oldSchema;
            }
            $aQuestionsValidations=$oRequest->getPost('validate',array());
            foreach($aQuestionsValidations as $iQid=>$sValue)
            {
                $bHidden=QuestionAttribute::model()->find("qid=:qid AND attribute='hidden'",array(":qid"=>$iQid));
                $oQuestion=Question::model()->find("sid=:sid AND qid=:qid and parent_qid = 0",array(":sid"=>$this->iSurveyId,":qid"=>"{$iQid}"));
                if($oQuestion && !$bHidden && $sValue=='hide')
                {
                    if($this->setQuestionHidden($oQuestion->qid)) {
                        $this->aResult['success'][]="{$oQuestion->title} was hide to respondant";
                    } else {
                        $this->aResult['warning'][]="{$oQuestion->title} unable to hide to respondant";
                    }
                    // Hide comment question
                    if($oQuestion && in_array($oQuestion->type,$this->aDelphiCodes))
                    {
                        foreach($this->aDelphiCodes as $sDelphiKey=>$aDelphiCode)
                        {
                            if(isset($aDelphiCode['hidevalidate']) && $aDelphiCode['hidevalidate'])
                            {
                                $oCommentQuestion = Question::model()->find(
                                    "sid=:sid AND title=:title and parent_qid = 0",
                                    array(":sid"=>$this->iSurveyId,":title"=>"{$oQuestion->title}{$sDelphiKey}")
                                );
                                if($oCommentQuestion)
                                    $this->setQuestionHidden($oCommentQuestion->qid);
                            }
                        }
                    }

                }
                elseif($oQuestion && $bHidden && $sValue=='show')
                {
                    if($this->setQuestionShown($iQid)) {
                        $this->aResult['success'][]="{$oQuestion->title} was shown to respondant";
                    }
                }
            }
            if($oldSchema) {
                $aQuestionsValidations=$oRequest->getPost('q',array());
                foreach($aQuestionsValidations as $iQid=>$aQuestionValidations)
                {
                    foreach($aQuestionValidations as $sType=>$aQuestionActions)
                    {
                        foreach($aQuestionActions as $sAction=>$sDo)
                        {
                            if($sAction=='select') {
                                $this->doQuestion($iQid,$sType,$sAction,$oldSchema,$sDo);
                            } elseif($sDo) {
                                $this->doQuestion($iQid,$sType,$sAction,$oldSchema);
                            }
                        }
                    }
                }
            }
            $aQuestionsValidations=$oRequest->getPost('commhist',array());
            foreach($aQuestionsValidations as $iQid=>$aQuestionAction)
            {
                $this->doCommentQuestion($iQid,$aQuestionAction);
            }
        }
        LimeExpressionManager::SetDirtyFlag();
        //~ $aData=array();
        //~ $this->displayContent($aData,array("result"));
    }

    /**
     * @deprecated
     */
    private function _renderPartial($aData=false,$views=false)
    {
        if(!$aData){
            $aData=array();
        }
        $aData['lang'] = array(
            "This survey is activated. You can not create question." => $this->gT("This survey is activated. You can not create question."),
            "No Delphi questions found. Are you sure to activate %s and set some value different of 0 for some question." => sprintf(
                $this->gT("No Delphi questions found. Are you sure to activate %s and set some value different of 0 for some question."),
                    "<a href='//manual.limesurvey.org/Assessments' rel='external' title='LimeSurvey manual'>".$this->gT("assessment")."</a>"
            ),
            "Success on :" => $this->gT("Success on :"),
            "Information :" => $this->gT("Information :"),
            "Warning on :" => $this->gT("Warning on :"),
            "Error on :" => $this->gT("Error on :"),
            "Success" => $this->gT("Success"),
            "Warning" => $this->gT("Warning"),
            "Error" => $this->gT("Error"),
        );
        $aData['surveyid']=$aData['iSurveyID']=$aData['iSurveyId'] = $this->iSurveyId;
        $aData['bSurveyActivated']=$this->bSurveyActivated;
        $aData['title'] = !empty($aData['title'] ) ? $aData['title']  : $this->gT("Iterative questionnaire");
        $aData['aResult']=$this->aResult;

        $content = "";
        foreach($views as $view){
            $content .= $this->renderPartial($view,$aData, true);
        }
        
        return $content;
        //~ Yii::app()->end();
    }

    /**
     * return the column name of a simple question if it exist in schema
     * @param CDbTableSchema
     * @param integer
     * @return string|null
     */
    private function getOldField(CDbTableSchema $oldSchema,$iQid)
    {
        foreach ($oldSchema->columns as $name => $column)
        {
            $pattern = '/([\d]+)X([\d]+)X([\d]+.*)/';
            $matches = array();
            if (preg_match($pattern, $name, $matches)) {
                if ($matches[3] == $iQid) {
                    return $column;
                }
            }
        }
        return null;
    }

    /**
     * Return answers information : key for code and answer text, assemsment value,
     * coiunt came for countOldAnswers function
     * @param integer
     * @param string
     * @param string
     * @return array
     */
    private function getOldAnswersInfo($iQid,$sType,$sField)
    {
        $aAnswers = array();
        switch ($sType) {
            case "Y":
                $aAnswers =
                    array(
                        "Y" => array(
                            'answer' => gt("Yes"),
                            'assessment_value' => $this->scoreforyes
                        ),
                        "N" => array(
                            'answer'=>gt("No"),
                            'assessment_value' => $this->scoreforno
                        )
                    );
                break;
            default:
                $oAnswersCode = Answer::model()->findAll(
                    array(
                        'condition' => "qid=:qid",
                        'order'=>'sortorder',
                        'params'=>array(":qid"=>$iQid)
                    )
                );
                foreach($oAnswersCode as $oAnswerCode) {
                    $oAnswerL10n = AnswerL10n::model()->find(
                        "aid = :aid and language = :language",
                        array(":aid" => $oAnswerCode->aid, ":language" => $this->language)
                    );
                    $aAnswers[$oAnswerCode->code] = array_merge(
                        $oAnswerL10n->attributes,
                        $oAnswerCode->attributes
                    );
                }
                // Add other ??
                $oQuestion = Question::model()->find("qid=:qid and parent_qid = 0",array(":qid"=>$iQid));
                if($oQuestion && $oQuestion->other=="Y") {
                    $aAnswers["-oth-"] = array(
                        "qid" => $iQid,
                        "code" => "-oth-",
                        "answer" => gt("Other"),
                        "sortorder" => 10000,
                        "assessment_value" => "0",
                        "language" => $this->language,
                    );
                }
                break;
        }
        foreach($aAnswers as $sCode => $aAnswer) {
            $aAnswers[$sCode]['count'] = $this->countOldAnswers($sField, $sCode);
        }
        return $aAnswers;
    }

    /**
     * Return the number of answer done on tableName selected
     * @param string
     * @param string
     * @return integer
     */
    private function countOldAnswers($sField, $sValue = "")
    {
        $sQuotedField = Yii::app()->db->quoteColumnName($sField);
        if(App()->request->getPost('withuncompleted')) {
            return PluginDynamic::model($this->sTableName)->count(
                "{$sQuotedField}=:field{$sField}",
                array(":field{$sField}"=>$sValue)
            );
        } else {
            return PluginDynamic::model($this->sTableName)->count(
                "submitdate IS NOT NULL AND {$sQuotedField}=:field{$sField}",
                array(":field{$sField}" => $sValue)
            );
        }
    }

    /**
     * TODO
     * @param string
     * @return string html to be used as list
     */
    private function getOldAnswerText($sField) {
        $sQuotedField = Yii::app()->db->quoteColumnName($sField);
        if(App()->request->getPost('withuncompleted')) {
            $aResult = App()->db->createCommand(
                "SELECT {$sQuotedField} FROM {$this->sTableName} WHERE {$sQuotedField} IS NOT NULL AND {$sQuotedField} != ''"
            )->queryAll();
        } else {
            $aResult = App()->db->createCommand(
                "SELECT {$sQuotedField} FROM {$this->sTableName} WHERE submitdate IS NOT NULL AND {$sQuotedField} IS NOT NULL AND {$sQuotedField}!=''"
            )->queryAll();
        }
        return $this->htmlListFromQueryAll($aResult);
    }

    /**
     * Return the table by answer value (with assesment) to be shown to user.
     * @param integer
     * @param string
     * @param string
     * @return string
     */
    private function getOldAnswerTable($iQid, $sType, $sLang)
    {
        $htmlOldAnswersTable = "";
        $oldSchema = $this->oldSchema;
        $oldField = $this->getOldField($oldSchema,$iQid);
        if($oldField && $oldField->name) {
            $aOldAnswers=$this->getOldAnswersInfo($iQid, $sType, $oldField->name);
            $iTotalValue = 0;
            foreach ($aOldAnswers as $aOldAnswer) {
                if($aOldAnswer['assessment_value'] != 0) {
                    $iTotalValue += $aOldAnswer['count'];
                }
            }
            if($iTotalValue>0) {
                $htmlOldAnswersTable = "<div class='table-responsive'><table class='aciq-table table table-striped table-bordered'><thead><tr><td></td>";
                $htmlOldAnswersTable.= CHtml::tag('th',array('class'=>"text-center"),$this->gT('Count','html',$sLang));
                $htmlOldAnswersTable.= CHtml::tag('th',array('class'=>"text-center"),'%');
                $htmlOldAnswersTable.= "</tr></thead><tbody>";
                foreach ($aOldAnswers as $aOldAnswer) {
                    if($aOldAnswer['assessment_value']!=0) {
                        $htmlOldAnswersTable.= "<tr>";
                        $htmlOldAnswersTable.= CHtml::tag('th',array('class'=>"text-left"),$aOldAnswer['answer']);
                        $htmlOldAnswersTable.= CHtml::tag('td',array('class'=>"text-center"),$aOldAnswer['count']);
                        $sPercentage=($iTotalValue>0) ? str_pad(number_format($aOldAnswer['count']/$iTotalValue*100),3," ",STR_PAD_LEFT)."%" : "/";
                        $htmlOldAnswersTable.= CHtml::tag('td',array('class'=>"text-center"),$sPercentage);
                        $htmlOldAnswersTable.= "</tr>";
                    }
                }
                $htmlOldAnswersTable.= "<tr>";
                $htmlOldAnswersTable.= CHtml::tag('th',array(),gT('Total'));
                $htmlOldAnswersTable.= CHtml::tag('td',array('class'=>"text-center"),$iTotalValue);
                $htmlOldAnswersTable.= CHtml::tag('td',array('class'=>"text-center"),"100%");
                $htmlOldAnswersTable.= "</tr>";
                $htmlOldAnswersTable.= "</tbody></table></div>";
            }
        }
        return $htmlOldAnswersTable;

    }

    /**
     * Get the delphi question : single choice with assesment value
     * @return Object[]
     */
    private function getDelphiQuestion()
    {
        static $aoQuestionsInfo;
        if (is_array($aoQuestionsInfo)) {
            return $aoQuestionsInfo;
        }
        $oCriteria = new CDbCriteria();
        $oCriteria->addCondition("t.sid = :sid AND parent_qid = 0");
        $oCriteria->params=array(":sid" => $this->iSurveyId);
        $oCriteria->addInCondition("type", self::$aValidQuestion);
        $oCriteria->order = "group_order asc, question_order asc";

        $oQuestions = Question::model()->resetScope()->with('group')->findAll($oCriteria);
        $aoQuestionsInfo = array();
        foreach($oQuestions as $oQuestion) {
            $key = "G" .
                str_pad($oQuestion->group->group_order, 5, "0", STR_PAD_LEFT) .
                "Q" .
                str_pad($oQuestion->question_order, 5, "0", STR_PAD_LEFT);
            $oAnswer = Answer::model()->count("qid=:qid and assessment_value<>0",array(":qid" => $oQuestion->qid));
            if($oAnswer) {
                $oLangQuestion = QuestionL10n::model()->find(
                    "qid=:qid and language=:language",
                    array(':qid' => $oQuestion->qid, ':language' => $this->language)
                );
                /* @todo : fix survey , show error ? */
                $aoQuestionsInfo[$key] = new stdClass();
                $aoQuestionsInfo[$key]->sid = $oQuestion->sid;
                $aoQuestionsInfo[$key]->gid = $oQuestion->gid;
                $aoQuestionsInfo[$key]->qid = $oQuestion->qid;
                $aoQuestionsInfo[$key]->title = $oQuestion->title;
                $aoQuestionsInfo[$key]->type = $oQuestion->type;
                $aoQuestionsInfo[$key]->question = $oLangQuestion->question;
                $aoQuestionsInfo[$key]->help = $oLangQuestion->help;
            }
        }
        return $aoQuestionsInfo;
    }

    /**
     * Get a comment question
     * @return Object[]
     */
    private function getCommentQuestion()
    {
        static $aoQuestionsInfo;
        if(is_array($aoQuestionsInfo)) {
            return $aoQuestionsInfo;
        }
        $oCriteria = new CDbCriteria();
        $oCriteria->addCondition("t.sid=:sid AND parent_qid=0");
        $oCriteria->params = array(":sid"=>$this->iSurveyId);
        $oCriteria->addInCondition("type",self::$aTextQuestion);
        $oCriteria->order="group_order asc, question_order asc";

        $oQuestions = Question::model()->resetScope()->with('group')->findAll($oCriteria);
        $aoQuestionsInfo=array();
        foreach($oQuestions as $oQuestion){
            $key = "G" . str_pad($oQuestion->group->group_order, 5, "0", STR_PAD_LEFT) .
                "Q".str_pad($oQuestion->question_order, 5, "0", STR_PAD_LEFT);
            $oLangQuestion = QuestionL10n::model()->find(
                "qid=:qid and language=:language",
                array(':qid' => $oQuestion->qid, ':language' => $this->language)
            );
            /* @todo : fix survey , show error ? */
            $aoQuestionsInfo[$key] = new stdClass();
            $aoQuestionsInfo[$key]->sid = $oQuestion->sid;
            $aoQuestionsInfo[$key]->gid = $oQuestion->gid;
            $aoQuestionsInfo[$key]->qid = $oQuestion->qid;
            $aoQuestionsInfo[$key]->title = $oQuestion->title;
            $aoQuestionsInfo[$key]->type = $oQuestion->type;
            $aoQuestionsInfo[$key]->question = $oLangQuestion->question;
            $aoQuestionsInfo[$key]->help = $oLangQuestion->help;
        }
        return $aoQuestionsInfo;
    }

    /* Get comment and delphi question
     * ordered by order
     * @return Object[]
     */
    private  function getAllQuestion()
    {
        $oDelphiQuestions = $this->getDelphiQuestion();
        $oCommentQuestions = $this->getCommentQuestion();
        $oAllQuestions = array_merge(
            $oDelphiQuestions,
            $oCommentQuestions
        );
        ksort ($oAllQuestions);
        return $oAllQuestions;
    }

    /**
     * do the question of type
     * @var integer qid : the related question
     * @var strint type : the question type 
     * @var string action : the action
     * @var mixed oldSchema ÷ the old datatable
     * @var mixed sDo : action to do 
     */
    private function doQuestion($iQid, $sType, $sAction, $oldSchema = NULL, $sDo = null)
    {
        // Validate the delphi question
        $oQuestionBase = Question::model()->find(
            "sid=:sid AND qid=:qid and parent_qid = 0",
            array(":sid" => $this->iSurveyId, ":qid" => $iQid)
        );

        if(!$oQuestionBase) {
            $this->addResult("No question {$iQid} in survey : {$sDo}", 'error');
            return false;
        }
        if ($sDo) {
            $sCode = $oQuestionBase->title;
            $oQuestion = Question::model()->find(
                "sid=:sid AND title=:title and parent_qid = 0",
                array(":sid" => $this->iSurveyId,":title" => "{$sCode}{$sType}")
            );
            if($oQuestion) {
                $oHidden = QuestionAttribute::model()->find("qid=:qid AND attribute='hidden'",array(":qid" => $oQuestion->qid));
            }
            $bHidden = (isset($oHidden) && $oHidden->value);
            switch($sDo) {
                case 'none':
                    if($oQuestion) {
                        $this->addResult(sprintf($this->gT("%s exist in survey."), $oQuestion->title), 'warning');
                    }
                    return;
                case 'hide':
                    if($oQuestion && !$bHidden) {
                        if($this->setQuestionHidden($oQuestion->qid)) {
                            $this->aResult['success'][] = sprintf($this->gT("%s was hide to respondant."), $oQuestion->title);
                        } else {
                            $this->aResult['warning'][] = sprintf($this->gT("%s unable to hide to respondant."), $oQuestion->title);
                        }
                    }
                    return;
                case 'create':
                    $sAction = 'createupdate';
                    break;
                case 'update':
                    if($bHidden) {
                        if($this->setQuestionShown($oQuestion->qid)) {
                            $this->aResult['success'][] = sprintf($this->gT("%s was shown to respondant."), $oQuestion->title);
                        } else {
                            $this->aResult['warning'][] = sprintf($this->gT("%s unable to shown to respondant."), $oQuestion->title);
                        }
                    }
                    $sAction='update';
                    break;
                case 'show':
                    if ($bHidden) {
                        if($this->setQuestionShown($oQuestion->qid)) {
                            $this->aResult['success'][] = sprintf($this->gT("%s was shown to respondant."), $oQuestion->title);
                        } else {
                            $this->aResult['warning'][] = sprintf($this->gT("%s unable to shown to respondant."), $oQuestion->title);
                        }
                    }
                    return;
                default:
                    $this->addResult(sprintf($this->gT("Unknow action %s %s %s %s in survey", $sDo, $iQid, $sType, $sAction),'warning'));
                    return false;
            }
        }
        $oRequest = $this->api->getRequest();
        $aScores = $oRequest->getPost('value');
        $sCode = $oQuestionBase->title;
        $iGid = $oQuestionBase->gid;
        $aDelphiKeys = array_keys($this->aDelphiCodes);
        $aDelphiCodes = $this->aDelphiCodes;

        if ($sAction=='create' || $sAction=='createupdate') {
            //Existing question
            $oQuestion = Question::model()->find(
                "sid=:sid AND title=:title and parent_qid = 0",
                array(":sid" => $this->iSurveyId, ":title" => "{$sCode}{$sType}")
            );
            if ($oQuestion) {
                $this->addResult(sprintf($this->gT("A question with code %s already exist in your survey, can not create a new one"), $sCode.$sType),'error');
                return false;
            }
            //Validate if we can add question
            if (isset($aDelphiCodes[$sType]['need'])) {
                $oQuestionExist = Question::model()->find(
                    "sid=:sid AND title=:title and parent_qid = 0",
                    array(":sid" => $this->iSurveyId, ":title" => "{$sCode}{$aDelphiCodes[$sType]['need']}")
                );
                if(!$oQuestionExist) {
                    $this->addResult(sprintf($this->gT("Question %s need a %s"), $sCode.$sType, $sCode.$aDelphiCodes[$sType]['need']));
                    return false;
                }
            }
            $iOrder = $oQuestionBase->question_order;
            if($sType=="comm") {
                $iOrder++;
            }
            if($iNewQid=$this->createQuestion($sCode,$sType,$iGid,$iOrder)) {
                $oSurvey = Survey::model()->findByPk($this->iSurveyId);
                $aLangs = $oSurvey->getAllLanguages();
                if (isset($aDelphiCodes[$sType]['hidden'])) {
                    $this->setQuestionHidden($iNewQid);
                }
                if (isset($aDelphiCodes[$sType]['condition'])) {
                    $this->setQuestionCondition($iNewQid,$sType,$sCode);
                }
            } else {
                $this->addResult(sprintf($this->gT("Question with %s can not be created"), $sCode.$sType),'error');
                return false;
            }
        }
        if($sAction=='update' || $sAction=='createupdate') {
            $oQuestion = Question::model()->find(
                "sid=:sid AND title=:title and parent_qid = 0",
                array(":sid" => $this->iSurveyId, ":title" => "{$sCode}{$sType}")
            );

            if(!$oQuestion) {
                $this->addResult(sprintf($this->gT("Question with code %s don't exist in your survey"), $sCode.$sType),'error');
                return false;
            }
            $oSurvey = Survey::model()->findByPk($this->iSurveyId);
            $aLangs = $oSurvey->getAllLanguages();
            // We have the id
            switch ($sType) {
                case 'res':
                    if(isset($aScores[$iQid]['score'])) {
                        $iCount = QuestionL10n::model()->updateAll(
                            array('question'=>$aScores[$iQid]['score']),
                            "qid=:qid",
                            array(":qid" => $oQuestion->qid)
                        );
                        $this->addResult(sprintf($this->gT("%s updated with %s"), $oQuestion->title, $aScores[$iQid]['score']), 'success');
                    } else {
                        $iCount = QuestionL10n::model()->updateAll(
                            array('question'=>""),
                            "qid=:qid",
                            array(":qid" => $oQuestion->qid)
                        );
                        $this->addResult(sprintf($this->gT("%s not updated with score : unable to find score"), $oQuestion->title), 'warning');
                    }
                    break;
                case 'hist':
                    $bGoood = true;
                    $oQuestionBase = Question::model()->find(
                        "qid=:qid and parent_qid = 0",
                        array(":qid" => $iQid)
                    );
                    if ($oQuestionBase) {
                        $aSuccessLang = array();
                        foreach($aLangs as $sLang) {
                            $oQuestionL10nBase = QuestionL10n::model()->find(
                                "qid=:qid AND language=:language",
                                array(":qid" => $iQid, ":language" => $sLang)
                            );
                            if ($oQuestionL10nBase) {
                                $newQuestionHelp = $oQuestionL10nBase->question;
                                if ($oldAnswerTable = $this->getOldAnswerTable($oQuestionBase->qid, $oQuestionBase->type, $sLang)) {
                                    $newQuestionHelp .= "<hr>";
                                    $newQuestionHelp .= $oldAnswerTable;
                                }
                                $newQuestionHelp = "<div class='aciq-content'>" . $newQuestionHelp . "</div>";
                                QuestionL10n::model()->updateAll(
                                    array('help' => $newQuestionHelp),
                                    "qid=:qid AND language=:language",
                                    array(":qid" => $oQuestion->qid, ":language" => $sLang)
                                );
                                $aSuccessLang[] = $sLang;
                            }else{
                                $this->addResult(sprintf($this->gT("Unable to find %s to update history for language %s."),$iQid,$sLang),'error');
                            }
                        }
                        $this->setQuestionDelphi($oQuestion->qid);
                        $this->addResult(sprintf($this->gT("%s (%s) - question help updated with list of answers."),$oQuestionBase->title.$sType,join(",",$aSuccessLang)),'success');
                    } else {
                        $bGoood = false;
                    }
                    if($bGoood) {
                        $this->addResult(sprintf($this->gT("%s (%s) - question help updated ."),$oQuestionBase->title.$sType,join(",",$aLangs)),'success');
                    }
                    break;
                case 'comm':
                    break;
                case 'comh':
                    // Find the old comm value
                    $oQuestionExist = Question::model()->find(
                        "sid=:sid AND title=:title and parent_qid = 0",
                        array(":sid" => $this->iSurveyId, ":title" => "{$sCode}comm")
                    );
                    if($oQuestionExist && $this->oldSchema) {
                        $sColumnName = $this->getOldField(
                            $this->oldSchema,
                            $oQuestionExist->qid
                        );
                        if($sColumnName) {
                            $baseQuestionText = $this->getOldAnswerText($sColumnName->name);
                            foreach($aLangs as $sLang) {
                                $newQuestionHelp = "<div class='aciq-content'>" . $baseQuestionText . "</div>";
                                QuestionL10n::model()->updateAll(
                                    array('help'=>$newQuestionHelp),
                                        "qid=:qid AND language=:language",
                                        array(":qid" => $oQuestion->qid, ":language" => $sLang)
                                    );
                            }

                            $this->setQuestionDelphi($oQuestion->qid);
                            $this->addResult(sprintf($this->gT("%s (%s) - question help updated with list of answers."),$oQuestionBase->title.$sType,join(",",$aLangs)),'success');
                        } else {
                            $newQuestionHelp = "";
                            $sColumnName = $this->getOldField(
                                $this->oldSchema,
                                $oQuestionExist->qid
                            );
                            Question::model()->updateAll(
                                array('help' => $newQuestionText),
                                "qid=:qid",array(":qid" => $oQuestion->qid)
                            );
                            $this->addResult(sprintf($this->gT("%s question help cleared: question was not found in old survey."),$oQuestionBase->title.$sType),'warning');
                        }

                    }
                    break;
                case 'cgd':
                    break;
                case 'cgdh':
                    // Find the old cgd value
                    $oQuestionExist = Question::model()->find(
                        "sid=:sid AND title=:title and parent_qid = 0",
                        array(":sid" => $this->iSurveyId, ":title" => "{$sCode}cgd")
                    );
                    if($oQuestionExist) {
                        $sColumnName=$this->getOldField($oldSchema,$oQuestionExist->qid);
                        $oQuestionToUpdate = Question::model()->find(
                            "sid=:sid AND title=:title and parent_qid = 0",
                            array(":sid" => $this->iSurveyId, ":title" => $oQuestionBase->title . $sType)
                        );
                        if($sColumnName && $oQuestionToUpdate) {
                            $baseQuestionText = $this->getOldAnswerText($sColumnName->name);
                            foreach($aLangs as $sLang) {
                                $newQuestionText = "<div class='aciq-accordion'>";
                                $newQuestionText .= "<p class='aciq-title comment-title'>".$this->get("commenthist_{$sLang}", 'Survey', $this->iSurveyId,$this->gT('Previous comment(s).','html',$sLang)).$sLang."</p>";
                                $newQuestionText .= "<div class='aciq-content'>".$baseQuestionText."</div>";
                                $newQuestionText .= "</div>";
                                QuestionL10n::model()->updateAll(
                                    array('question' => $newQuestionText),
                                    "qid=:qid AND language=:language",
                                    array(":qid"=>$oQuestionToUpdate->qid,":language"=>$sLang)
                                );
                            }
                            $oQuestionDelphi = Question::model()->find(
                                'sid=:sid AND title=:title and parent_qid = 0',
                                array(":sid" => $this->iSurveyId, ":title" => $oQuestionBase->title .$sType)
                            );
                            if($oQuestionDelphi) {
                                $this->setQuestionDelphi($oQuestionDelphi->qid);
                                $this->addResult(sprintf($this->gT("%s (%s) - question help updated with list of answers."),$oQuestionBase->title.$sType,join(",",$aLangs)),'success');
                            } else {
                                $this->addResult(sprintf($this->gT("%s question help was not updated: unable to find question."),$oQuestionBase->title.$sType),'warning');
                            }
                        }
                    }
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * Create commant question
     * @param integer $iQid
     * @param string action
     * @return void
     */
    private function doCommentQuestion($iQid, $sAction)
    {
        $oSurvey = Survey::model()->findByPk($this->iSurveyId);
        $aLangs = $oSurvey->getAllLanguages();
        $oQuestionBase = Question::model()->find(
            "sid=:sid AND qid=:qid and parent_qid = 0",
            array(":sid" => $this->iSurveyId, ":qid"=>$iQid));
        if (!$oQuestionBase) {
            $this->addResult(sprintf($this->gT("No question %s in survey"),$iQid), 'error');
            return false;
        }
        $sCode = $oQuestionBase->title;
        $oQuestion = Question::model()->find(
            "sid=:sid AND title=:title and parent_qid = 0",
            array(":sid" => $this->iSurveyId, ":title"=>"{$sCode}h")
        );
        if($oQuestion) {
            $oHidden = QuestionAttribute::model()->find(
                "qid=:qid AND attribute='hidden'",
                array(":qid" => $oQuestion->qid)
            );
        }
        $bHidden = !empty($oHidden->value);
        switch($sAction) {
            case 'none':
                if($oQuestion) {
                    $this->addResult(sprintf($this->gT("%s exist in survey."), $oQuestion->title), 'warning');
                }
                return;
            case 'hide':
                if($oQuestion && !$bHidden) {
                    if($this->setQuestionHidden($oQuestion->qid)) {
                        $this->aResult['success'][] = sprintf($this->gT("%s was hide to respondant"), $oQuestion->title);
                    } else {
                        $this->aResult['warning'][] = sprintf($this->gT("%s unable to hide to respondant"), $oQuestion->title);
                    }
                }
                return;
            case 'create':
                //Existing question
                if($oQuestion) {
                    $this->addResult(sprintf($this->gT("A question with code %s already exist in your survey, can not create a new one"), $sCode."h"),'error');
                    return false;
                }
                $iOrder = $oQuestionBase->question_order;
                if(strlen($oQuestionBase->title) > 4 && substr($oQuestionBase->title, -4) === "comm") {
                    //Try to find all question
                    $oQuestionDelphi = Question::model()->find(
                        "sid=:sid and title=:title and parent_qid = 0",
                        array(":sid"=>$this->iSurveyId, ":title"=>substr($oQuestionBase->title,0, strlen($oQuestionBase->title)-4))
                    );
                    if($oQuestionDelphi) {
                        $iOrder = $oQuestionDelphi->question_order;
                    }
                }
                if($iNewQid = $this->createQuestion($oQuestionBase->title, "h", $oQuestionBase->gid, $iOrder)) {
                    $oQuestion = Question::model()->find(
                        "sid=:sid AND qid=:qid and parent_qid = 0",
                        array(":sid"=> $this->iSurveyId, ":qid"=>$iNewQid)
                    );
                } else{
                    $this->addResult(sprintf($this->gT("Unable to create %s question in survey."), $oQuestion->title), 'error');
                    return;
                }
            case 'update':
                if ($oQuestion) {
                    if ($bHidden) {
                        if($this->setQuestionShown($oQuestion->qid)) {
                            $this->aResult['success'][] = sprintf($this->gT("%s was shown to respondant"),$oQuestion->title);
                        } else {
                            $this->aResult['warning'][] = sprintf($this->gT("%s unable to shown to respondant"),$oQuestion->title);
                        }
                    }
                    if($oQuestionBase && $this->oldSchema) {
                        $sColumnName = $this->getOldField($this->oldSchema, $oQuestionBase->qid);
                        if($sColumnName) {
                            $baseQuestionText = $this->getOldAnswerText($sColumnName->name);
                            foreach($aLangs as $sLang) {
                                $oQuestionCommentLang = QuestionL10n::model()->find(
                                    "language=:language AND qid=:qid",
                                    array(":language"=>$sLang, ":qid"=>$oQuestionBase->qid));
                                if($oQuestionCommentLang) {
                                    $newQuestionHelp = "<div class='aciq-content'>" .
                                        "<div class='aciq-question-comment'>" . $oQuestionCommentLang->question . "</div>" .
                                        $baseQuestionText .
                                        "</div>";
                                } else {
                                    $newQuestionHelp = "<div class='aciq-content'>" . $baseQuestionText . "</div>";
                                }
                                QuestionL10n::model()->updateAll(
                                    array('help' => $newQuestionHelp),
                                    "qid=:qid AND language=:language",
                                    array(":qid" => $oQuestion->qid, ":language" => $sLang)
                                );
                            }
                            $oQuestionDelphi = Question::model()->find(
                                'sid=:sid AND qid=:qid and parent_qid = 0',
                                array(":sid" => $this->iSurveyId, ":qid" => $oQuestion->qid)
                            );
                            $this->setQuestionDelphi($oQuestion->qid);
                            $this->addResult(sprintf($this->gT("%s (%s) - question help updated with list of answers."), $oQuestionBase->title."h",join(",",$aLangs)),'success');
                        }
                        else
                        {
                            $newQuestionHelp = "";
                            QuestionL10n::model()->updateAll(array('help'=>$newQuestionText),"qid=:qid",array(":qid" => $oQuestion->qid));
                            $this->addResult(sprintf($this->gT("%s question help cleared: question was not found in old survey."),$oQuestionBase->title."h"),'warning');
                        }
                    }
                }
                break;
            case 'show':
                if($bHidden) {
                    if($this->setQuestionShown($oQuestion->qid)) {
                        $this->aResult['success'][]=sprintf($this->gT("%s was shown to respondant"),$oQuestion->title);
                    } else {
                        $this->aResult['warning'][]=sprintf($this->gT("%s unable to shown to respondant"),$oQuestion->title);
                    }
                }
                break;
            default:
                $this->addResult("Unknow action {$sDo} {$iQid} {$sType} {$sAction} in survey",'warning');
                $this->log("Unknow action {$sDo} {$iQid} {$sType} {$sAction} in survey",'error');
                return false;
        }
    }

    /**
     * Create a question
     * @param string sCode
     * @param string sType (h|hist|comm|comh)
     * @param integer iGid
     * @param integer iOrder
     */
    private function createQuestion($sCode, $sType, $iGid, $iOrder)
    {
        //Need to renumber all questions on or after this
        $sQuery = "UPDATE {{questions}} SET question_order=question_order+1 WHERE sid=:sid AND gid=:gid AND parent_qid = 0 AND question_order >= :order";
        App()->db->createCommand($sQuery)
            ->bindValues(
                array(
                    ':sid' => $this->iSurveyId,
                    ':gid' => $iGid,
                    ':order' => $iOrder
                )
            )
            ->query();
        if ($sType=="h") {
            $sNewQuestionType = "X";
        } else {
            $sNewQuestionType = $this->aDelphiCodes[$sType]['questiontype'];
        }
        switch ($sType) {
            case 'hist':
                break;
            case 'comm':
                break;
            // NOT in $this->aDelphiCodes!
            case 'comh':
                $oQuestionComment = Question::model()->find(
                    "sid=:sid and title=:title and parent_qid = 0",
                    array(":sid" => $this->iSurveyId, ":title" => $sCode."comm")
                );
                break;
            case "h":
                $oQuestionComment = Question::model()->find(
                    "sid=:sid and title=:title and parent_qid = 0",
                    array(":sid" => $this->iSurveyId, ":title" => $sCode)
                );
                break;
            default:
                break;
        }
        $oQuestion= new Question;
        $oQuestion->sid = $this->iSurveyId;
        $oQuestion->gid = $iGid;
        $oQuestion->title = $sCode.$sType;
        $oQuestion->preg = '';
        $oQuestion->other = 'N';
        $oQuestion->mandatory = 'N';
        $oQuestion->type=$sNewQuestionType;
        $oQuestion->question_order = $iOrder;

        $oSurvey = Survey::model()->findByPk($this->iSurveyId);
        if($oQuestion->save()) {
            $iQuestionId = $oQuestion->qid;
            
            $aLang = $oSurvey->getAllLanguages();
            foreach($aLang as $sLang) {
                $newQuestionText = "";
                switch ($sType) {
                    case 'hist':
                        $newQuestionText = $this->get("historytext_{$sLang}", 'Survey', $this->iSurveyId,"");
                        if(trim($newQuestionText) == "") {
                            $newQuestionText = $this->gT('Previous proposal and results','html',$sLang);
                        }
                        $newQuestionText = "<p class='aciq-default'>".$newQuestionText."</p>";
                        break;
                    case 'comm':
                        $newQuestionText = $this->get("commenttext_{$sLang}", 'Survey', $this->iSurveyId,"");
                        if(trim($newQuestionText) == "") {
                            $newQuestionText = $this->gT('Please explain why.','html',$sLang);
                        }
                        break;
                    case 'commh':
                        $newQuestionText = $this->get("commenthist_{$sLang}", 'Survey', $this->iSurveyId,"");
                        if(trim($newQuestionText) == "") {
                            $newQuestionText = $this->gT('Previous comment(s).','html',$sLang);
                        }
                        $newQuestionText = "<p class='aciq-default'>".$newQuestionText."</p>";
                        //~ if($oQuestionComment) {
                            //~ $oQuestionL10nComment = QuestionL10n::model()->find(
                                //~ "qid=:qid AND language=:language",array(":qid" => $oQuestionComment->qid, ":language" => $sLang)
                            //~ );
                            //~ if($oQuestionL10nComment && $oQuestionL10nComment->question) {
                                //~ $newQuestionText .= "<div class='aciq-historycomment'>" . $oQuestionL10nComment->question . "</div>";
                            //~ }
                        //~ }
                        break;
                    case 'h':
                        $newQuestionText = $this->get("commenthist_{$sLang}", 'Survey', $this->iSurveyId,"");
                        if(trim($newQuestionText) == "") {
                            $newQuestionText = $this->gT('Previous comment(s).','html',$sLang);
                        }
                        $newQuestionText = "<p class='aciq-default'>".$newQuestionText."</p>";
                        //~ if($oQuestionComment) {
                            //~ $oQuestionL10nComment = QuestionL10n::model()->find(
                                //~ "qid=:qid AND language=:language",array(":qid" => $oQuestionComment->qid, ":language" => $sLang)
                            //~ );
                            //~ if($oQuestionL10nComment && $oQuestionL10nComment->question) {
                                //~ $newQuestionText .= "<div class='aciq-historycomment'>" . $oQuestionL10nComment->question . "</div>";
                            //~ }
                        //~ }
                        break;
                    default:
                        $newQuestionText = "";
                        break;
                }
                $oLangQuestion= new QuestionL10n;
                $oLangQuestion->qid = $oQuestion->qid;
                $oLangQuestion->question = $newQuestionText;
                $oLangQuestion->help = "";
                $oLangQuestion->language = $sLang;
                if(!$oLangQuestion->save()) {
                    $this->log(\CVarDumper::dumpAsString($oLangQuestion->getErrors()),'error');
                }
            }
            $this->addResult(sprintf($this->gT("Created question %s."),$sCode.$sType),'success');
            return $iQuestionId;
        }
        $this->addResult("Unable to create question {$sCode}{$sType}, please contact the software developer.",'error',$oQuestion->getErrors());
        $this->log("Unable to create question {$sCode}{$sType}",'error');
        $this->log(\CVarDumper::dumpAsString($oQuestion->getErrors()),'error');
    }
    private function setQuestionHidden($iQid)
    {
        $oQuestion = Question::model()->find(
            "sid=:sid AND qid=:qid and parent_qid = 0",
            array(":sid" => $this->iSurveyId, ":qid" => $iQid)
        );
        if (!$oQuestion) {
            return;
        }
        $oAttribute = QuestionAttribute::model()->find(
            "qid=:qid AND attribute='hidden'",
            array(":qid" => $iQid)
        );
        if(!$oAttribute) {
            $oAttribute=new QuestionAttribute;
            $oAttribute->qid = $iQid;
            $oAttribute->attribute = "hidden";
        }
        $oAttribute->value=1;
        if($oAttribute->save()) {
            return true;
        } else {
            $this->addResult("Unable to set {$iQid} hidden",'error',$oAttribute->getErrors());
        }
    }
    private function setQuestionDelphi($iQid)
    {
        $oQuestion = Question::model()->find(
            "sid=:sid AND qid=:qid and parent_qid = 0",
            array(":sid" => $this->iSurveyId, ":qid" => $iQid)
        );
        if (!$oQuestion) {
            return;
        }
        $oAttribute = QuestionAttribute::model()->find(
            "qid=:qid AND attribute='iterativeQuestion'",
            array(":qid" => $iQid)
        );
        if(!$oAttribute) {
            $oAttribute = new QuestionAttribute;
            $oAttribute->qid = $iQid;
            $oAttribute->attribute = "iterativeQuestion";
        }
        $oAttribute->value = 1;
        if($oAttribute->save()) {
            return true;
        } else {
            $this->addResult(sprintf($this->gT("Unable to set %s iterativeQuestion"),$iQid),'error',$oAttribute->getErrors());
            $this->log(\CVarDumper::dumpAsString($oAttribute->getErrors()),'error');
        }
    }

    /**
     * Show a question, removing hide attribute
     * @param integer $iQid
     * @returun boolean|null
     */
    private function setQuestionShown($iQid)
    {
        $oQuestion = Question::model()->find(
            "sid=:sid AND qid=:qid and parent_qid = 0",
            array(":sid"=>$this->iSurveyId, ":qid"=>$iQid)
        );
        if(!$oQuestion) {
            return;
        }
        $iAttribute = QuestionAttribute::model()->deleteAll("qid=:qid AND attribute='hidden'",array(":qid" => $iQid));
        if($iAttribute) {
           return true;
        }
        return false;
    }

    /**
     * Set the default condition for a question
     * @param integer $iQid
     * @param string $sType base type of question
     * @param string $sCode code of primary question
     * @returun boolean|null
     */
    private function setQuestionCondition($iQid, $sType, $sCode="")
    {
        if (empty($this->aDelphiCodes[$sType]['condition'])) {
            return;
        }
        $sCondition = $this->aDelphiCodes[$sType]['condition'];
        $oQuestion = Question::model()->find(
            "sid=:sid AND title=:title and parent_qid = 0",
            array(":sid"=> $this->iSurveyId, ":title"=> "{$sCode}{$sType}")
        );
        if ($oQuestion) {
            $sCondition = str_replace("{QCODE}", $sCode, $sCondition);
            $updatedCount = Question::model()->updateAll(
                array('relevance'=>$sCondition),
                "sid=:sid AND qid=:qid",
                array(":sid" => $this->iSurveyId, ":qid" => $iQid)
            );
            return $updatedCount;
        } else {
            $this->addResult("Unable to find {$sCode}{$sType} to set condition",'error');
        }
    }
    /**
     * Return the check box setting
     * @param integer $iQid : base question qid
     * @param string $sCode : base question title
     * @param string $sType : new question type
     * @return array
     */
    private function getCheckQuestionSettings($iQid,$sCode,$sType)
    {
        $aQuestionsSettings = array();
        $oQuestionResult = Question::model()->find(
            "sid=:sid AND title=:title and parent_qid = 0",
            array(":sid" => $this->iSurveyId, ":title" => "{$sCode}{$sType}")
        );
        if($oQuestionResult) {
            $aQuestionsSettings["q[{$iQid}][{$sType}][view]"]['type'] = 'info';
            $aQuestionsSettings["q[{$iQid}][{$sType}][view]"]['content'] = $this->aLang[$sType]['view'];
        } elseif(!$this->bSurveyActivated && isset($this->aDelphiCodes[$sType]['create'])) {
            $aQuestionsSettings["q[{$iQid}][{$sType}][create]"]['type'] = 'checkbox';
            $aQuestionsSettings["q[{$iQid}][{$sType}][create]"]['label'] = $this->aLang[$sType]['create'];
            $aQuestionsSettings["q[{$iQid}][{$sType}][create]"]['current'] = $this->aDelphiCodes[$sType]['create'];
        }
        return $aQuestionsSettings;
    }
    /**
     * Return the validation setting
     * @param \Question $oQuestion
     * @return array
     */
    private function getValidateQuestionSettings($oQuestion)
    {
        $oldSchema = $this->oldSchema;
        $aQuestionsSettings = array();
        $sFieldName = $oQuestion->sid."X".$oQuestion->gid."X".$oQuestion->qid;
        $aQuestionsInfo[$oQuestion->qid] = array();
        if ($aQuestionsInfo[$oQuestion->qid]['oldField'] = $this->getOldField($oldSchema,$oQuestion->qid)) {
            // Test if question is hidden (already validated)
            $oAttributeHidden = QuestionAttribute::model()->find(
                "qid=:qid AND attribute='hidden'",
                array(":qid" => $oQuestion->qid)
            );
            $oQuestionLanguage = QuestionL10n::model()->find(
                "qid = :qid AND language = :language",
                array(":qid" => $oQuestion->qid, ":language" => $this->language)
            );
            $sQuestionTextTitle = str_replace("'",'’',FlattenText($oQuestionLanguage->question));
            $sQuestionText = ellipsize($sQuestionTextTitle,80);
            if($oQuestion->title !== preg_replace("/[^_a-zA-Z0-9]/", "", $oQuestion->title)) {
                $aQuestionsSettings["q_{$oQuestion->qid}"]['type'] = 'info';
                $aQuestionsSettings["q_{$oQuestion->qid}"]['content'] = CHtml::tag('div',array('class'=>'questiontitle','title'=>$sQuestionTextTitle),sprintf($this->gT("% since LimeSurvey 2.05 title allow only alphanumeric (no space, no dot ..)"),"<strong>".sprintf($this->gT("Invalid title : %s"),$oQuestion->title)."</strong>"));
            } else {
                // Get the % and evaluate note
                $aOldAnswers = $this->getOldAnswersInfo(
                    $oQuestion->qid,
                    $oQuestion->type,
                    $aQuestionsInfo[$oQuestion->qid]['oldField']->name
                );
                $iTotal = 0;
                $iTotalValue = 0;
                $iTotalNeg = 0;
                $iTotalPos = 0;

                foreach ($aOldAnswers as $aOldAnswer) {
                    $iTotal += $aOldAnswer['count'];
                    if($aOldAnswer['assessment_value']) {
                        $iTotalValue += $aOldAnswer['count'];
                    }
                    if(intval($aOldAnswer['assessment_value'])<0) {
                        $iTotalNeg += $aOldAnswer['count'];
                    }
                    if(intval($aOldAnswer['assessment_value'])>0) {
                        $iTotalPos += $aOldAnswer['count'];
                    }
                }
                $sHtmlTable = "";
                $hiddenPart = "";
                $bValidate = false;
                $iScore = 0;

                /*
                if($iTotalValue && false)  {
                    $sHtmlTable.="<table class='aciq-table clearfix table table-striped table-bordered'><thead><td></td><th>count</th><th>%</th></thead><tbody>";
                    $iTotalPosPC=number_format($iTotalPos/$iTotalValue*100)."%";
                    $iTotalNegPC=number_format($iTotalNeg/$iTotalValue*100)."%";
                    $sHtmlTable.="<tr><th>Upper than 0</th><td>{$iTotalPos}</td><td>{$iTotalPosPC}</td></tr>";
                    $sHtmlTable.="<tr><th>Lesser than 0</th><td>{$iTotalNeg}</td><td>{$iTotalNegPC}</td></tr>";
                    $sHtmlTable.="</tbody></table>";
                }
                */
                $sHtmlTable .= "<table class='aciq-table clearfix table table-striped table-bordered'><thead><td></td><th>" . gt("Count") . "</th><th>%</th>";
                $sHtmlTable .= "<th>% cumulative</th>";
                if($iTotalValue) {
                    $sHtmlTable .= "<th>% with value</th>";
                    $sHtmlTable .= "<th>% cumulative with value</th>";
                }
                $sHtmlTable .= "</thead><tbody>";
                $cumulBrut = 0;
                $cumulValue = 0;
                foreach ($aOldAnswers as $sCode=>$aOldAnswer) {
                    $sHtmlTable .= "<tr>";
                    $sHtmlTable .= "<th title='".str_replace("'",'’',FlattenText($aOldAnswer['answer']))."'>{$sCode} : <small>".ellipsize(FlattenText($aOldAnswer['answer']),60)."</small></th>";
                    $sHtmlTable .= "<td>{$aOldAnswer['count']}</td>";
                    if ($iTotal>0) {
                        $sHtmlTable .="<td>" . number_format($aOldAnswer['count']/$iTotal*100) . "%"."</td>";
                        $cumulBrut+=$aOldAnswer['count'];
                        $sHtmlTable .="<td>" . number_format($cumulBrut/$iTotal*100) . "%"."</td>";
                    } else {
                        $sHtmlTable.="<td>/</td><td>/</td>";
                    }
                    if($iTotalValue>0) {
                        if (intval($aOldAnswer['assessment_value'])!=0) {
                            $sHtmlTable.="<td>".number_format($aOldAnswer['count']/$iTotalValue*100)."%"."</td>";
                            $cumulValue+=$aOldAnswer['count'];
                            $sHtmlTable.="<td>".number_format($cumulValue/$iTotalValue*100)."%"."</td>";
                        }else{
                            $sHtmlTable.="<td>/</td><td>/</td>";
                        }
                    }
                    $sHtmlTable.="</tr>";
                }
                $sHtmlTable.="<tr><th>".gt("Total")."</th>";
                $sHtmlTable.="<td>{$iTotal}</td>";
                $sHtmlTable.="<td>{$iTotal}</td>";
                $sHtmlTable.="<td>{$iTotal}</td>";
                if($iTotalValue>0) {
                    $sHtmlTable.="<td>{$iTotalValue}</td>";
                    $sHtmlTable.="<td>{$iTotalValue}</td>";
                }
                $sHtmlTable.="</tr>";
                $sHtmlTable.="</tbody></table>";

                $aQuestionsSettings["q_{$oQuestion->qid}"]['type']='info';
                $aQuestionsSettings["q_{$oQuestion->qid}"]['content']="<div class='questiontitle' title='{$sQuestionTextTitle}'><strong class='label label-info'>{$oQuestion->title}</strong> : {$sQuestionText}</div><div class='oldresult  clearfix'>"
                    .$sHtmlTable
                    ."</div>"
                    .$hiddenPart;

                $aQuestionsSettings["validate[{$oQuestion->qid}]"]['type']='select';
                $aQuestionsSettings["validate[{$oQuestion->qid}]"]['label']=$this->gT("Ask the question again in this new round");
                $aQuestionsSettings["validate[{$oQuestion->qid}]"]['options']=array(
                    'hide'=>"No, do not ask it",
                    'show'=>"Yes, ask it",
                );
                $aQuestionsSettings["validate[{$oQuestion->qid}]"]['current']=($oAttributeHidden && $oAttributeHidden->value) ? 'hide' : 'show';

                foreach($this->aDelphiCodes as $sDelphiCode=>$aSettings) {
                    $aQuestionsSettings=array_merge($aQuestionsSettings,$this->getComplementValidateQuestionSettings($oQuestion->qid,$oQuestion->title,$sDelphiCode));
                }
            }
        } else {
            $oAttributeHidden = QuestionAttribute::model()->find("qid=:qid AND attribute='hidden'",array(":qid"=>$oQuestion->qid));
            $oQuestionLanguage = QuestionL10n::model()->find(
                "qid = :qid AND language = :language",
                array(":qid" => $oQuestion->qid, ":language" => $this->language)
            );
            $sQuestionText = ellipsize(flattenText($oQuestionLanguage->question),80);
            $aQuestionsSettings["q_".$oQuestion->qid]['type']='info';
            $aQuestionsSettings["q_".$oQuestion->qid]['content']=sprintf($this->gT("%s : no corresponding question."),CHtml::tag("strong",array('class'=>"questiontitle",'title'=>$sQuestionText),$oQuestion->title));
        }
        return $aQuestionsSettings;
    }
    /**
    * @param $iQid : base question qid
    * @param $sCode : base question title
    * @param $sType : new question type
    * @param $sValue
    * @return
    */
    private function getComplementValidateQuestionSettings($iQid, $sCode, $sType, $sValue = NULL)
    {
        $aQuestionsSettings = array();
        $aDelphiCodes = $this->aDelphiCodes;
        $oQuestionResult = Question::model()->find(
            "sid=:sid AND title=:title and parent_qid = 0",
            array(":sid" => $this->iSurveyId, ":title"=> "{$sCode}{$sType}")
        );
        if (isset($this->aDelphiCodes[$sType]['select'])) {
            $sLabel = "<span class='label'>{$sCode}{$sType}</span>" . $this->aDelphiCodes[$sType]['select']['label'];
            // Add list of comment
            if($sType == "comh") {
                // Find the old comm value
                $oQuestionExist = Question::model()->find(
                    "sid=:sid AND title=:title and parent_qid = 0",
                    array(":sid" => $this->iSurveyId, ":title" => "{$sCode}comm")
                );
                if($oQuestionExist && $this->oldSchema) {
                    $sColumnName = $this->getOldField($this->oldSchema, $oQuestionExist->qid);
                    if($sColumnName) {
                        $baseQuestionText = $this->getOldAnswerText($sColumnName->name);
                        if($baseQuestionText) {
                            $sLabel="<div class='aciqtitle'>$baseQuestionText</div><span class='label' data-aciqtitle='true'>".$this->gT("See previous comments")."</span> {$sLabel}";
                        }else {
                            $sLabel="<span class='label label-warning'>".$this->gT("No previous answers")."</span> {$sLabel}";
                        }
                    } else {
                        $sLabel="<span class='label label-warning'>".$this->gT("No previous answers")."</span> {$sLabel}";
                    }
                } else {
                    $sLabel="<span class='label label-warning'>".$this->gT("No previous question")."</span> {$sLabel}";
                }
            }

            $aQuestionsSettings["q[{$iQid}][{$sType}][select]"]['type'] = 'select';
            $aQuestionsSettings["q[{$iQid}][{$sType}][select]"]['label'] = $sLabel;
            $aOptions = $this->aDelphiCodes[$sType]['select']['options'];
            if($oQuestionResult) {
                unset($aOptions['none']);
                unset($aOptions['create']);
                if(QuestionAttribute::model()->find("qid=:qid AND attribute='hidden'",array(":qid"=>$oQuestionResult->qid))) {
                    $aQuestionsSettings["q[{$iQid}][{$sType}][select]"]['current'] = 'hide';
                } else {
                    if($sType=="hist" && $this->bUpdateHistory) {
                      $aQuestionsSettings["q[{$iQid}][{$sType}][select]"]['current'] = 'update';
                    } else {
                      $aQuestionsSettings["q[{$iQid}][{$sType}][select]"]['current'] = 'show';
                    }
                }
            } else {
                unset($aOptions['hide']);
                unset($aOptions['update']);
                unset($aOptions['show']);
                if ($sType=="hist" && $this->bUpdateHistory) {
                  $aQuestionsSettings["q[{$iQid}][{$sType}][select]"]['current'] = 'create';
                } else {
                  $aQuestionsSettings["q[{$iQid}][{$sType}][select]"]['current'] = 'none';
                }
            }
            if (count($aOptions)) {
                $aQuestionsSettings["q[{$iQid}][{$sType}][select]"]['options'] = $aOptions;
            } else {
                unset($aQuestionsSettings["q[{$iQid}][{$sType}][select]"]);
            }
        } else {
            if($oQuestionResult) {
                if(isset($this->aDelphiCodes[$sType]['update'])) {
                    $aQuestionsSettings["q[{$iQid}][{$sType}][update]"]['type'] = 'checkbox';
                    $aQuestionsSettings["q[{$iQid}][{$sType}][update]"]['label'] = $this->aLang[$sType]['update'];
                    $aQuestionsSettings["q[{$iQid}][{$sType}][update]"]['current'] = $this->aDelphiCodes[$sType]['update'];
                } else {
                    $aQuestionsSettings["q[{$iQid}][{$sType}][view]"]['type'] = 'info';
                    $aQuestionsSettings["q[{$iQid}][{$sType}][view]"]['content'] = $this->aLang[$sType]['view'];
                }
            } elseif (!$this->bSurveyActivated && isset($aDelphiCodes[$sType]['createupdate'])) {
                // Default current
                $bCurrent = $this->aDelphiCodes[$sType]['createupdate'];
                if(isset($aDelphiCodes[$sType]['need'])) {
                    $oQuestionExist = Question::model()->find(
                        "sid=:sid AND title=:title and parent_qid = 0",
                        array(":sid"=>$this->iSurveyId, ":title" => "{$sCode}{$aDelphiCodes[$sType]['need']}")
                    );
                    $bCurrent = $this->aDelphiCodes[$sType]['createupdate'] && (bool)$oQuestionExist;
                }
                $aQuestionsSettings["q[{$iQid}][{$sType}][createupdate]"]['type'] = 'checkbox';
                $aQuestionsSettings["q[{$iQid}][{$sType}][createupdate]"]['label'] = $this->aLang[$sType]['createupdate'];
                $aQuestionsSettings["q[{$iQid}][{$sType}][createupdate]"]['current'] = $bCurrent;
            }
        }
        return $aQuestionsSettings;
    }
    private function getCommentQuestionSettings($oQuestion) {
        $oldSchema = $this->oldSchema;
        $aQuestionsSettings = array();
        $sFieldName = $oQuestion->sid."X".$oQuestion->gid."X".$oQuestion->qid;
        $aQuestionsInfo[$oQuestion->qid] = array();
        $oAttributeHidden = QuestionAttribute::model()->find(
            "qid=:qid AND attribute='hidden'",
            array(":qid"=>$oQuestion->qid)
        );
        $oQuestionLanguage = QuestionL10n::model()->find(
            "qid = :qid AND language = :language",
            array(":qid" => $oQuestion->qid, ":language" => $this->language)
        );
        $sQuestionTextTitle = Chtml::encode(FlattenText($oQuestionLanguage->question));
        $sQuestionText = ellipsize($sQuestionTextTitle,50);
        if($oQuestion->title !== preg_replace("/[^_a-zA-Z0-9]/", "", $oQuestion->title)) {
            $aQuestionsSettings["q_{$oQuestion->qid}"]['type'] = 'info';
            $aQuestionsSettings["q_{$oQuestion->qid}"]['content'] = CHtml::tag('div',array('class'=>'questiontitle','title'=>$sQuestionTextTitle),"<strong>Invalid title : {$oQuestion->title}</strong> : LimeSurvey 2.05 title allow only alphanumeric (no space, no dot ..)");
        } else {
            $aQuestionsSettings["validate[{$oQuestion->qid}]"]['type'] = 'select';
            $aQuestionsSettings["validate[{$oQuestion->qid}]"]['label']="<div class='' title='{$sQuestionTextTitle}'>".$this->gT("Display this question")."</div>";
            $aQuestionsSettings["validate[{$oQuestion->qid}]"]['options']=array(
                'hide'=>"Don't ask this question",
                'show'=>"Ask this question",
            );
            $aQuestionsSettings["validate[{$oQuestion->qid}]"]['current'] = ($oAttributeHidden && $oAttributeHidden->value) ? 'hide' : 'show';
            // Adding history question
        }
        if($aQuestionsInfo[$oQuestion->qid]['oldField']=$this->getOldField($oldSchema,$oQuestion->qid)) {
            $sColumnName = $this->getOldField($this->oldSchema,$oQuestion->qid);
            if($sColumnName) {
                $oldAnswerText = $this->getOldAnswerText($sColumnName->name);
            } else {
                $oldAnswerText = null;
            }
            // Do label
            if ($oldAnswerText) {
                $sLabel="<span class='label label-info'>{$oQuestion->title}h</span>"
                        . $this->gT("Show comments from the previous round (automatic)")
                        . " <a class='label label-default' role='button' data-toggle='collapse' href='#previous{$oQuestion->title}' aria-expanded='false' aria-controls='collapseExample'><i class='fa fa-eye'> </i> ".$this->gT("See")."</a> "
                        . "<div class='collapse' id='previous{$oQuestion->title}'><div class='well text-left small'>$oldAnswerText</div></div>";
            } else {
                $sLabel="<span class='label label-warning'>{$oQuestion->title}h</span>".$this->gT("No comments from the previous round (automatic)");
            }
            // Adding history question only if we have old field
            // Find if history exist
            $oHistoryQuestion = Question::model()->find(
                "sid=:sid AND title=:title and parent_qid = 0",
                array(":sid"=>$this->iSurveyId,":title"=>"{$oQuestion->title}h")
            );
            $aSettings = array(
                'type' => "select",
                'label' => $sLabel,
                'options' => array(
                    'none' => $this->gT("No, do not create it"),
                    'hide' => $this->gT("No, do not display it"),
                    'create' => $this->gT("Yes, create and display it"),
                    'update' => $this->gT("Yes, display it"),
                    'show' => $this->gT("Yes, display it (but don’t update)"),
                ),
            );
            if($oHistoryQuestion) {
                $oAttributeHidden = QuestionAttribute::model()->find(
                    "qid=:qid AND attribute='hidden'",
                    array(":qid" => $oHistoryQuestion->qid)
                );
                unset($aSettings['options']['none']);
                unset($aSettings['options']['create']);
                if($oAttributeHidden && $oAttributeHidden->value && $oldAnswerText) {
                  $aSettings['current'] = 'hide';
                } elseif($this->bUpdateHistory) {
                  $aSettings['current'] = 'update';
                } else {
                  $aSettings['current'] = 'show';
                }
            } else {
                unset($aSettings['options']['hide']);
                unset($aSettings['options']['update']);
                unset($aSettings['options']['show']); 
                if($this->bUpdateHistory && $oldAnswerText) {
                  $aSettings['current'] = 'create';
                } else {
                  $aSettings['current'] = 'none';
                }
            }
            $aQuestionsSettings["commhist[{$oQuestion->qid}]"]=$aSettings;
        }

        return $aQuestionsSettings;
    }

    /**
     * set an html list
     * @param string[] $aString
     * @param array $htmlOptions
     * @return string (html)
     */
    private function htmlListFromQueryAll($aStrings, $htmlOptions=array())
    {
        $sHtmlList=array();
        if(!empty($aStrings)) {
            foreach($aStrings as $aString) {
#                if(is_string($aString))
#                    $sHtmlList[]=CHtml::tag("li",array(),$aString);
#                else
                    $sHtmlList[] = CHtml::tag("li",array(),current($aString));
            }
            return Chtml::tag("ul",array('class'=>'aciq-answerstext'),implode("\n", $sHtmlList));
        }
    }

    /**
     * Adding a result
     * @param string $sTring
     * @param string $sType (error|warning|success)
     * @param mixed $oTrace
     * @retrun void
     */
    private function addResult($sString,$sType='info',$oTrace=NULL)
    {
        if (in_array($sType,array('success','info','warning','error')) && is_string($sString) && $sString) {
            $this->aResult[$sType][] = $sString;
        } elseif (is_numeric($sType)) {
            $this->aResult['question'][] = $sType;
        }
        if($oTrace) {
            $this->log(\CVarDumper::dumpAsString($oTrace),'info');
        }
    }

    /**
     * Check admin access to a survey
     * @Throw CHttpException
     * @return void
     */
    private function checkAccess() {
        if(is_null($this->iSurveyId)) {
            throw new CHttpException(500,"Invalid Survey Id." );
        }
        $oSurvey = Survey::model()->findByPk($this->iSurveyId);
        if(!$oSurvey) {
            throw new CHttpException(404,"Invalid Survey Id." );
        }
        if($oSurvey->active == "Y") {
            $this->bSurveyActivated = true;
        }
        if( !Permission::model()->hasSurveyPermission($this->iSurveyId, 'surveycontent', 'update')) {
            throw new CHttpException(401,"Invalid Survey Id." );
        }
    }

    /**
     * Check if is compatible
     * @return boolean
     */
    private function checkCompatibilityAdmin()
    {
        return version_compare(App()->getConfig('versionnumber'), "4.0.0",">=");
    }
}

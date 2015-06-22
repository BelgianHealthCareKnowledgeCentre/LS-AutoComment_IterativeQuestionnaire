<?php
/**
 * kceDelphi Plugin for LimeSurvey
 * A simplified Delphi method for KCE
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2014 Denis Chenu <http://sondages.pro>
 * @copyright 2014 Belgian Health Care Knowledge Centre (KCE) <http://kce.fgov.be>
 * @license GPL v3
 * @version 2.2
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
class kceDelphi extends PluginBase { 

    protected $storage = 'DbStorage';
    static protected $name = 'Delphi for KCE';
    static protected $description = 'Activate the Delphi method for Delphi - v3.0';

    private $iSurveyId=false;
    private $bSurveyActivated=false;
    private $sTableName="";
    private $sError="Unknow error.";
    private $sLanguage="";
    private static $aValidQuestion=array("!","L","O");
    private static $aTextQuestion=array("S","T","U");

    private $oldSchema;
    private $aResult=array('success'=>array(),'warning'=>array(),'error'=>array());
    //~ private $validatescore,$scoreforyes,$scoreforno;

    private $aDelphiCodes=array(
                            //~ 'res'=>array(
                                //~ 'create'=>false,
                                //~ 'createupdate'=>false,
                                //~ 'update'=>true,
                                //~ 'questiontype'=>"*",
                                //~ 'hidden'=>true,
                            //~ ),
                            'hist'=>array(
                                'questiontype'=>"X",
                                'select'=>array(
                                    'label'=>"Question formulation from the previous round",
                                    'options'=>array(
                                        'none'=>'No creation of this question',
                                        'hide'=>'Hide this question',
                                        'create'=>"Create question with actual question text (and Show it)",
                                        'update'=>"Update question with actual question text (and Show it)",
                                        "show"=>"Show the question",
                                    ),
                                ),
                            ),
                            'comm'=>array(
                                'questiontype'=>"T",
                                'create'=>true,
                                'select'=>array(
                                        'label'=>"Comment question (automatic)",
                                        'options'=>array(
                                            'none'=>'No creation of this question',
                                            'create'=>"Create question with default text (and Show it)",
                                        ),
                                    ),
                                'condition'=>"{QCODE}.valueNAOK< 0",
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
    protected $settings = array(
        //~ 'validatescore'=>array(
            //~ 'type'=>'float',
            //~ 'label'=>'Minimal score for a proposal to be validated',
            //~ 'default'=>0,
        //~ ),
        'commenttext'=>array(
            'type'=>'string',
            'label'=>'Default question text for comments',
            'class' => 'large',
            'default'=>'Can you explain why you disagree with this proposal.',
        ),
        //~ 'commentalttext'=>array(
            //~ 'type'=>'string',
            //~ 'label'=>'Default question text for alternative comments',
            //~ 'class' => 'large',
            //~ 'default'=>'Can you explain why you agree with this proposal.',
        //~ ),
        'historytext'=>array(
            'type'=>'string',
            'label'=>'Default text before history',
            'class' => 'large',
            'default'=>'Previous proposal',
        ),
        'commenthist'=>array(
            'type'=>'string',
            'label'=>'Default header for list of comments',
            'class' => 'large',
            'default'=>'Previous comments.',
        ),
        //~ 'commentalthist'=>array(
            //~ 'type'=>'string',
            //~ 'label'=>'Default header for list of alternative comments',
            //~ 'class' => 'large',
            //~ 'default'=>'Previous comments of people who agree.',
        //~ ),
    );
    public function __construct(PluginManager $manager, $id) {
        parent::__construct($manager, $id);
        $this->subscribe('beforeSurveySettings');
        $this->subscribe('newSurveySettings');
        //Can call plugin
        $this->subscribe('newDirectRequest');
        // Add js and css
        $this->subscribe('beforeSurveyPage');
    }

    public function beforeSurveyPage()
    {
        $assetJsUrl = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/publickcedelphi.js');
        $assetCssUrl = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/publickcedelphi.css');
        Yii::app()->clientScript->registerScriptFile($assetJsUrl,CClientScript::POS_END);
        Yii::app()->clientScript->registerCssFile($assetCssUrl);
    }
    public function beforeSurveySettings()
    {
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
                    'label'=>"Sentence added before old proposal history ({$sLang})",
                    'class' => 'large',
                    'current' => $this->get("historytext_{$sLang}", 'Survey', $oEvent->get('survey'),$this->get('historytext',null,null,$this->settings['historytext']['default'])),
                );
            }
            foreach($aLangs as $sLang)
            {
                $aSettings["commenttext_{$sLang}"]=array(
                    'type'=>'string',
                    'label'=>"Sentence for the comments question show if user choose a answer with value less than 0 ({$sLang})",
                    'class' => 'large',
                    'current' => $this->get("commenttext_{$sLang}", 'Survey', $oEvent->get('survey'),$this->get('commenttext',null,null,$this->settings['commenttext']['default'])),
                );
            }

            foreach($aLangs as $sLang)
            {
                $aSettings["commenthist_{$sLang}"]=array(
                    'type'=>'string',
                    'label'=>"Sentence added before comment list ({$sLang})",
                    'class' => 'large',
                    'current' => $this->get("commenthist_{$sLang}", 'Survey', $oEvent->get('survey'),$this->get('commenthist',null,null,$this->settings['commenthist']['default'])),
                );
            }

            // Did we have an old survey ?
            $aTables = App()->getApi()->getOldResponseTables($iSurveyId);
            if(count($aTables)>0)
            {
                $aSettings['launch']=array(
                    'type'=>'link',
                    'link'=>$this->api->createUrl('plugins/direct', array('plugin' => 'kceDelphi','surveyid'=>$iSurveyId, 'function' => 'view')),
                    'label'=>'Update the survey according to an old answer table',
                    'help'=>'Attention, you lost actual settings',
                    'class'=>array('btn-link'),
                );
            }
            $aSettings['check']=array(
                'type'=>'link',
                'link'=>$this->api->createUrl('plugins/direct', array('plugin' => 'kceDelphi','surveyid'=>$iSurveyId, 'function' => 'check')),
                'label'=>'Update the survey to add needed question',
                'help'=>'Attention, you lost actual settings',
                'class'=>array('btn-link'),
            );
            

            // Add the string for each language
            $oEvent->set("surveysettings.{$this->id}", array('name' => get_class($this),'settings'=>$aSettings));
        }
        elseif( $oSurvey && $oSurvey->assessments!='Y' )
        {
            $clang = Yii::app()->lang;
            $aSettings['info']=array(
                'type'=>'info',
                'content'=>"<div class='alert'><strong>".$clang->gT("Assessments mode not activated")."</strong> <br />".sprintf($clang->gT("Assessment mode for this survey is not activated. You can activate it in the %s survey settings %s (tab 'Notification & data management')."),'<a href="'.Yii::app()->createUrl('admin/survey/sa/editsurveysettings/surveyid/'.$iSurveyId).'#notification">','</a>')."</div>",
            );
            $oEvent->set("surveysettings.{$this->id}", array('name' => get_class($this),'settings'=>$aSettings));
        }
        $assetUrl = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets');
        Yii::app()->clientScript->registerScriptFile($assetUrl . '/fixfloat.js',CClientScript::POS_END);
        Yii::app()->clientScript->registerCssFile($assetUrl . '/kcedelphi.css');
        Yii::app()->clientScript->registerCssFile($assetUrl . '/settingsfix.css');
    }
    public function newSurveySettings()
    {
        $event = $this->event;
        foreach ($event->get('settings') as $name => $value)
        {
            /* In order use survey setting, if not set, use global, if not set use default */
            $default=$event->get($name,null,null,isset($this->settings[$name]['default'])?$this->settings[$name]['default']:NULL);
            $this->set($name, $value, 'Survey', $event->get('survey'),$default);
        }
    }

    public function newDirectRequest()
    {
        $oEvent = $this->event;
        $sAction=$oEvent->get('function');

        if ($oEvent->get('target') != "kceDelphi")
            return;

        $this->iSurveyId=Yii::app()->request->getParam('surveyid');
        $oSurvey=Survey::model()->findByPk($this->iSurveyId);
        if(!$oSurvey)
        {
            throw new CHttpException(404,"Invalid Survey Id." );
        }
        if($oSurvey->active=="Y")
            $this->bSurveyActivated=true;
        // We have survey , test access
        if( !Permission::model()->hasSurveyPermission($this->iSurveyId, 'surveycontent', 'update'))
        {
            Yii::app()->setFlashMessage("Access error : you don't have suffisant rigth to update survey content.",'error');
            App()->controller->redirect(array('admin/survey','sa'=>'view','surveyid'=>$this->iSurveyId));
        }
        //~ $this->validatescore=$this->get('validatescore','Survey',$this->iSurveyId,$oEvent->get('validatescore',$this->settings['validatescore']['default']));
#        $this->scoreforyes=$this->get('scoreforyes','Survey',$this->iSurveyId,$oEvent->get('scoreforyes',$this->settings['scoreforyes']['default']));
#        $this->scoreforno=$this->get('scoreforno','Survey',$this->iSurveyId,$oEvent->get('scoreforno',$this->settings['scoreforno']['default']));


        $aOtherLanguage=explode(" ",$oSurvey->additional_languages);
        if(in_array(Yii::app()->lang->langcode,$aOtherLanguage))
            $this->sLanguage=Yii::app()->lang->langcode;
        else
            $this->sLanguage=$oSurvey->language;
        if($sAction=='view')
            $this->actionView();
        elseif($sAction=='validate')
            $this->actionValidate();
        elseif($sAction=='update')
            $this->actionUpdate();
        elseif($sAction=='check')
            $this->actionCheck();
        else
            throw new CHttpException(404,'Unknow action');
    }

    public function actionCheck()
    {
    
        $oRequest = $this->api->getRequest();

        if($oRequest->getPost('cancel'))
        {
            App()->controller->redirect(array('admin/survey','sa'=>'view','surveyid'=>$this->iSurveyId));
        }
        if($oRequest->getIsPostRequest() && $oRequest->getPost('confirm'))
        {
            $aQuestionsValidations=$oRequest->getPost('q',array());
            foreach($aQuestionsValidations as $iQid=>$aQuestionValidations)
            {
                foreach($aQuestionValidations as $sType=>$aQuestionActions)
                {
                    foreach($aQuestionActions as $sAction=>$sDo)
                    {
                        if($sDo)
                            $this->doQuestion($iQid,$sType,$sAction);
                    }
                }
            }
            Yii::app()->setFlashMessage("Survey updated");
        }
        $oQuestions=$this->getDelphiQuestion();
        $aQuestionsSettings=array();
        $aQuestionsInfo=array();
        foreach($oQuestions as $oQuestion)
        {
            $sFieldName=$oQuestion->sid."X".$oQuestion->gid."X".$oQuestion->qid;
            $aQuestionsInfo[$oQuestion->qid]=array();
            // Test if question is hidden (already validated)
            $oAttributeHidden=QuestionAttribute::model()->find("qid=:qid AND attribute='hidden'",array(":qid"=>$oQuestion->qid));
            $sQuestionTextTitle=FlattenText($oQuestion->question);
            $sQuestionText=ellipsize($sQuestionTextTitle,80);
            if($oQuestion->title!==preg_replace("/[^_a-zA-Z0-9]/", "", $oQuestion->title))
            {
                $aQuestionsSettings["q_{$oQuestion->qid}"]['type']='info';
                $aQuestionsSettings["q_{$oQuestion->qid}"]['content']=CHtml::tag('div',array('class'=>'questiontitle','title'=>$sQuestionTextTitle),"<strong>Invalid title : {$oQuestion->title}</strong> : LimeSurvey 2.05 title allow only alphanumeric (no space, no dot ..)");
            }
            elseif($oAttributeHidden && $oAttributeHidden->value)
            {
                $aQuestionsSettings["q_{$oQuestion->qid}"]['type']='info';
                $aQuestionsSettings["q_{$oQuestion->qid}"]['content']=CHtml::tag('div',array('class'=>'questiontitle','title'=>$sQuestionTextTitle),"<strong>Validated question {$oQuestion->title}</strong> : {$sQuestionText}");
            }
            else
            {
                $aQuestionsSettings["q_{$oQuestion->qid}"]['type']='info';
                $aQuestionsSettings["q_{$oQuestion->qid}"]['content']=CHtml::tag('div',array('class'=>'questiontitle','title'=>$sQuestionTextTitle),"<strong>{$oQuestion->title}</strong> : {$sQuestionText}");
                foreach($this->aDelphiCodes as $sDelphiCode=>$aSettings)
                {
                    $aQuestionsSettings=array_merge($aQuestionsSettings,$this->getCheckQuestionSettings($oQuestion->qid,$oQuestion->title,$sDelphiCode));
                }
            }
        }
#        echo "<pre>";
#        print_r($aQuestionsSettings);
#        echo "</pre>";
#        die();
        $aData['aSettings']=$aQuestionsSettings;
        $aData['aResult']=$this->aResult;
     
        $aData['updateUrl']=$this->api->createUrl('plugins/direct', array('plugin' => 'kceDelphi','surveyid'=>$this->iSurveyId, 'function' => 'check'));
        $this->displayContent($aData,array("validate"));
    }
    /**
    * Show the form
    * 
    **/
    public function actionView()
    {
        //$baseSchema = SurveyDynamic::model($this->iSurveyId)->getTableSchema();
        $aTables = App()->getApi()->getOldResponseTables($this->iSurveyId);
        if(count($aTables))
            rsort ($aTables);
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
            );
            $aData['buttons'] = array(
                gT('Validate question before import') => array(
                    'name' => 'confirm'
                ),
                gT('Cancel') => array(
                    'name' => 'cancel'
                ),
            );
        }else{
            $aData['settings']['oldsurveytable'] = array(
                'type' => 'info',
                'content' => CHtml::tag('div',array('class'=>'alert'),'You have old survey table, but no answer inside'),
            );
            $aData['buttons'] = array(
                gT('Cancel') => array(
                    'name' => 'cancel'
                ),
            );
        }
        $aData['updateUrl']=$this->api->createUrl('plugins/direct', array('plugin' => 'kceDelphi','surveyid'=>$this->iSurveyId, 'function' => 'validate'));
        $this->displayContent($aData,array("select"));
    }
    /**
    * Validate survey before updating
    * 
    **/
    public function actionValidate()
    {
        if(Yii::app()->request->getPost('confirm'))
        {
            $sTableName=$this->sTableName=Yii::app()->request->getPost('oldsurveytable');
            $aTables = App()->getApi()->getOldResponseTables($this->iSurveyId);
            if(!in_array($sTableName,$aTables)){
                Yii::app()->setFlashMessage("Bad table name.",'error');
                App()->controller->redirect($this->api->createUrl('plugins/direct', array('plugin' => 'kceDelphi','surveyid'=>$this->iSurveyId, 'function' => 'view')));
            }
            if(Yii::app()->request->getPost('validate'))
                $this->actionUpdate();
            $oldTable = PluginDynamic::model($sTableName);
            $this->oldSchema = $oldSchema = $oldTable->getTableSchema();

            // Ok get the information ...
            $oQuestions=$this->getAllQuestion();
            $aQuestions=array();
            $aQuestionsSettings=array();
            $aQuestionsInfo=array();

            foreach($oQuestions as $oQuestion){
                if(in_array($oQuestion->type,self::$aValidQuestion))
                {
                    $aQuestionsSettings=array_merge($aQuestionsSettings,$this->getValidateQuestionSettings($oQuestion));
                }
                elseif(in_array($oQuestion->type,self::$aTextQuestion))
                {
                    $aQuestionsSettings=array_merge($aQuestionsSettings,$this->getCommentQuestionSettings($oQuestion));
                }
                else
                {
                    
                }
            }
            $aSurveySettings['oldsurveytable']=array(
                'label' => gT('Source table'),
                'type' => 'select',
                'options' => array($sTableName=>$sTableName),
                'current' => $sTableName,
                'class'=>'hidden'
            );
            $aData['aSettings']=array_merge($aQuestionsSettings,$aSurveySettings);
            $aData['updateUrl']=$this->api->createUrl('plugins/direct', array('plugin' => 'kceDelphi','surveyid'=>$this->iSurveyId, 'function' => 'validate'));
            LimeExpressionManager::SetDirtyFlag();
            $this->displayContent($aData,array("validate"));
        }
        else
        {
            App()->controller->redirect(array('/admin/survey/sa/view','surveyid'=>$this->iSurveyId));
        }
    }
    /**
    * Update survey with old survey
    * 
    **/
    public function actionUpdate()
    {

        $oRequest = $this->api->getRequest();
        if($oRequest->getPost('cancel'))
        {
            App()->controller->redirect(array('admin/survey','sa'=>'view','surveyid'=>$this->iSurveyId));
        }
        if($oRequest->getIsPostRequest() && $oRequest->getPost('confirm'))
        {
            if(!$this->oldSchema)
            {
                $sTableName=$this->sTableName=Yii::app()->request->getPost('oldsurveytable');
                $aTables = App()->getApi()->getOldResponseTables($this->iSurveyId);
                if(!in_array($sTableName,$aTables)){
                    Yii::app()->setFlashMessage("Bad table name.",'error');
                    App()->controller->redirect($this->api->createUrl('plugins/direct', array('plugin' => 'kceDelphi','surveyid'=>$this->iSurveyId, 'function' => 'view')));
                }

                $oldTable = PluginDynamic::model($sTableName);
                $this->oldSchema=$oldSchema = $oldTable->getTableSchema();
            }else
            {
                $oldSchema=$this->oldSchema;
            }
            $aQuestionsValidations=$oRequest->getPost('validate',array());
            foreach($aQuestionsValidations as $iQid=>$sValue)
            {
                $bHidden=QuestionAttribute::model()->find("qid=:qid AND attribute='hidden'",array(":qid"=>$iQid));
                $oQuestion=Question::model()->find("sid=:sid AND qid=:qid",array(":sid"=>$this->iSurveyId,":qid"=>"{$iQid}"));

                if($oQuestion && !$bHidden && $sValue=='hide')
                {
                    if($this->setQuestionHidden($oQuestion->qid))
                        $this->aResult['success'][]="{$oQuestion->title} was hide to respondant";
                    else
                        $this->aResult['warning'][]="{$oQuestion->title} unable to hide to respondant";
                    // Hide comment question
                    if($oQuestion && in_array($oQuestion->type,$this->aDelphiCodes))
                    {
                        foreach($this->aDelphiCodes as $sDelphiKey=>$aDelphiCode)
                        {
                            if(isset($aDelphiCode['hidevalidate']) && $aDelphiCode['hidevalidate'])
                            {
                                $oCommentQuestion=Question::model()->find("sid=:sid AND title=:title",array(":sid"=>$this->iSurveyId,":title"=>"{$oQuestion->title}{$sDelphiKey}"));
                                if($oCommentQuestion)
                                    $this->setQuestionHidden($oCommentQuestion->qid);
                            }
                        }
                    }
                    
                }
                elseif($oQuestion && $bHidden && $sValue=='show')
                {
                    if($this->setQuestionShown($iQid))
                        $this->aResult['success'][]="{$oQuestion->title} was shown to respondant";
                }
            }
            $aQuestionsValidations=$oRequest->getPost('q',array());
            foreach($aQuestionsValidations as $iQid=>$aQuestionValidations)
            {
                foreach($aQuestionValidations as $sType=>$aQuestionActions)
                {
                    foreach($aQuestionActions as $sAction=>$sDo)
                    {
                        if($sAction=='select')
                            $this->doQuestion($iQid,$sType,$sAction,$oldSchema,$sDo);
                        elseif($sDo)
                            $this->doQuestion($iQid,$sType,$sAction,$oldSchema);
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
    private function displayContent($aData=false,$views=false)
    {
        // TODO : improve and move to PluginsController
        if(!$aData){$aData=array();}
        $aData['surveyid']=$aData['iSurveyID']=$aData['iSurveyId'] = $this->iSurveyId;
        $aData['bSurveyActivated']=$this->bSurveyActivated;
        $oAdminController=new AdminController('admin');
        $oCommonAction = new Survey_Common_Action($oAdminController,'survey');

        $aData['clang'] = $clang = Yii::app()->lang;
        $aData['sImageURL'] = Yii::app()->getConfig('adminimageurl');
        $aData['aResult']=$this->aResult;

        ob_start();
        header("Content-type: text/html; charset=UTF-8"); // needed for correct UTF-8 encoding
        $assetUrl = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets');
        Yii::app()->clientScript->registerScriptFile($assetUrl . '/kcedelphi.js',CClientScript::POS_END);
        Yii::app()->clientScript->registerCssFile($assetUrl . '/kcedelphi.css');
        Yii::app()->clientScript->registerCssFile($assetUrl . '/settingsfix.css');
        // When debugging or adapt javascript
        //Yii::app()->getClientScript()->registerScriptFile(Yii::app()->getConfig('publicurl')."plugins/kceDelphi/assets/kcedelphi.js",CClientScript::POS_END);
        //Yii::app()->getClientScript()->registerScriptFile(Yii::app()->getConfig('publicurl')."plugins/kceDelphi/assets/kcedelphi.css");
        $oAdminController->_getAdminHeader();
        $oAdminController->_showadminmenu($this->iSurveyId);
        if($this->iSurveyId)
            $oCommonAction->_surveybar($this->iSurveyId);
        foreach($views as $view){
            Yii::setPathOfAlias("views.{$view}", dirname(__FILE__).DIRECTORY_SEPARATOR."views".DIRECTORY_SEPARATOR.$view);
            $oAdminController->renderPartial("views.{$view}",$aData);
        }
        $oAdminController->_loadEndScripts();
        $oAdminController->_getAdminFooter('http://manual.limesurvey.org', $clang->gT('LimeSurvey online manual'));
        $sOutput = ob_get_contents();
        ob_clean();
        App()->getClientScript()->render($sOutput);
        echo $sOutput;
        Yii::app()->end();
    }

    private function getOldField(CDbTableSchema $oldSchema,$iQid)
    {
        foreach ($oldSchema->columns as $name => $column)
        {
            $pattern = '/([\d]+)X([\d]+)X([\d]+.*)/';
            $matches = array();
            if (preg_match($pattern, $name, $matches))
            {
                if ($matches[3] == $iQid)
                {
                    return $column;
                }
            }
        }
    }
    private function getOldAnswersInfo($iQid,$sType,$sField)
    {
        $aAnswers=array();
        switch ($sType)
        {
            case "Y":
                $aAnswers=array("Y"=>array('answer'=>gt("Yes"),'assessment_value'=>$this->scoreforyes),"N"=>array('answer'=>gt("No"),'assessment_value'=>$this->scoreforno));
                break;
            default:
                $oAnswersCode=Answer::model()->findAll(array('condition'=>"qid=:qid AND language=:language",'order'=>'sortorder','params'=>array(":qid"=>$iQid,":language"=>$this->sLanguage)));
                foreach($oAnswersCode as $oAnswerCode)
                {
                    $aAnswers[$oAnswerCode->code]=$oAnswerCode->attributes;
                }
                break;
        }
        foreach($aAnswers as $sCode=>$aAnswer)
        {
            $aAnswers[$sCode]['count']=$this->countOldAnswers($sField,$sCode);
        }

        return $aAnswers;
    }
    private function countOldAnswers($sField,$sValue="")
    {
        $sQuotedField=Yii::app()->db->quoteColumnName($sField);
        return PluginDynamic::model($this->sTableName)->count("submitdate IS NOT NULL AND {$sQuotedField}=:field{$sField}", array(":field{$sField}"=>$sValue));
    }
    private function getOldAnswerText($sField)
    {
        $sQuotedField=Yii::app()->db->quoteColumnName($sField);
        //return Yii::app()->db->createCommand("SELECT {$sQuotedField} FROM {{{$this->sTableName}}} WHERE {$sQuotedField} IS NOT NULL AND  {$sQuotedField}!=''")->queryAll();
        //Problem on prefix
        $aResult=Yii::app()->db->createCommand("SELECT {$sQuotedField} FROM {$this->sTableName} WHERE submitdate IS NOT NULL AND {$sQuotedField} IS NOT NULL AND {$sQuotedField}!=''")->queryAll();
        return $this->htmlListFromQueryAll($aResult);
    }
    private function getDelphiQuestion()
    {
        static $aoQuestionsInfo;
        if(is_array($aoQuestionsInfo))
            return $aoQuestionsInfo;
        $oCriteria = new CDbCriteria();
        $oCriteria->addCondition("t.sid=:sid AND t.language=:language");
        $oCriteria->params=array(":sid"=>$this->iSurveyId,":language"=>$this->sLanguage);
        $oCriteria->addInCondition("type",self::$aValidQuestion);
        $oCriteria->order="group_order asc, question_order asc";

        $oQuestions=Question::model()->with('groups')->findAll($oCriteria);
        $aoQuestionsInfo=array();
        foreach($oQuestions as $oQuestion){
            $key="G".str_pad($oQuestion->groups->group_order,5,"0",STR_PAD_LEFT)."Q".str_pad($oQuestion->question_order,5,"0",STR_PAD_LEFT);
            $oAnswer=Answer::model()->find("qid=:qid and assessment_value!=0",array(":qid"=>$oQuestion->qid));
            if($oAnswer)
                $aoQuestionsInfo[$key]=$oQuestion;
        }
        return $aoQuestionsInfo;
    }
    private function getCommentQuestion()
    {
        static $aoQuestionsInfo;
        if(is_array($aoQuestionsInfo))
            return $aoQuestionsInfo;
        $oCriteria = new CDbCriteria();
        $oCriteria->addCondition("t.sid=:sid AND t.language=:language and parent_qid=0");
        $oCriteria->params=array(":sid"=>$this->iSurveyId,":language"=>$this->sLanguage);
        $oCriteria->addInCondition("type",self::$aTextQuestion);

        $oCriteria->order="group_order asc, question_order asc";

        $oQuestions=Question::model()->with('groups')->findAll($oCriteria);
        $aoQuestionsInfo=array();
        foreach($oQuestions as $oQuestion){
            $key="G".str_pad($oQuestion->groups->group_order,5,"0",STR_PAD_LEFT)."Q".str_pad($oQuestion->question_order,5,"0",STR_PAD_LEFT);
            $aoQuestionsInfo[$key]=$oQuestion;
        }
        return $aoQuestionsInfo;
    }
    private  function getAllQuestion()
    {
        $oDelphiQuestions=$this->getDelphiQuestion();
        $oCommentQuestions=$this->getCommentQuestion();
        $oAllQuestions = array_merge($oDelphiQuestions, $oCommentQuestions);
        ksort ($oAllQuestions);
        return $oAllQuestions;
    }
    private function doQuestion($iQid,$sType,$sAction,$oldSchema=NULL,$sDo=null)
    {
        // Validate the delphi question
        $oQuestionBase=Question::model()->find("sid=:sid AND language=:language AND qid=:qid",array(":sid"=>$this->iSurveyId,":language"=>$this->sLanguage,":qid"=>"{$iQid}"));
        if(!$oQuestionBase)
        {
            $this->addResult("No question {$iQid} in survey",'error');
            return false;
        }
        if($sDo)
        {
            $sCode=$oQuestionBase->title;
            $oQuestion=Question::model()->find("sid=:sid AND language=:language AND title=:title",array(":sid"=>$this->iSurveyId,":language"=>$this->sLanguage,":title"=>"{$sCode}{$sType}"));
            if($oQuestion)
                $oHidden=QuestionAttribute::model()->find("qid=:qid AND attribute='hidden'",array(":qid"=>$oQuestion->qid));
            $bHidden=( isset($oHidden) && $oHidden->value)? true : false;
            switch($sDo)
            {
                case 'none':
                    if($oQuestion)
                        $this->addResult("{$oQuestion->title} exist in survey.",'warning');
                    return;
                case 'hide':
                    if($oQuestion && !$bHidden)
                    {
                        if($this->setQuestionHidden($oQuestion->qid))
                            $this->aResult['success'][]="{$oQuestion->title} was hide to respondant";
                        else
                            $this->aResult['warning'][]="{$oQuestion->title} unable to hide to respondant";
                    }
                    return;
                case 'create':
                    $sAction='createupdate';
                    break;
                case 'update':
                    if($bHidden)
                    {
                        if($this->setQuestionShown($oQuestion->qid))
                            $this->aResult['success'][]="{$oQuestion->title} was shown to respondant";
                        else
                            $this->aResult['warning'][]="{$oQuestion->title} unable to shown to respondant";
                    }
                    $sAction='update';
                    break;
                case 'show':
                    if($bHidden)
                    {
                        if($this->setQuestionShown($oQuestion->qid))
                            $this->aResult['success'][]="{$oQuestion->title} was shown to respondant";
                        else
                            $this->aResult['warning'][]="{$oQuestion->title} unable to shown to respondant";
                    }
                    return;
                default:
                    $this->addResult("Unknow action {$sDo} {$iQid} {$sType} {$sAction} in survey",'warning');
                    return false;
            }
        }
        $oRequest = $this->api->getRequest();
        $aScores = $oRequest->getPost('value');
        $sCode=$oQuestionBase->title;
        $iGid=$oQuestionBase->gid;
        $aDelphiKeys=array_keys($this->aDelphiCodes);
        $aDelphiCodes=$this->aDelphiCodes;
        
        if($sAction=='create' || $sAction=='createupdate')
        {
            //Existing question
            $oQuestion=Question::model()->find("sid=:sid AND language=:language AND title=:title",array(":sid"=>$this->iSurveyId,":language"=>$this->sLanguage,":title"=>"{$sCode}{$sType}"));
            if($oQuestion)
            {
                $this->addResult("A question with code {$sCode}{$sType} already exist in your survey, can not create a new one",'error');
                return false;
            }
            //Validate if we can add question
            if(isset($aDelphiCodes[$sType]['need']))
            {
                $oQuestionExist=Question::model()->find("sid=:sid AND language=:language AND title=:title",array(":sid"=>$this->iSurveyId,":language"=>$this->sLanguage,":title"=>"{$sCode}{$aDelphiCodes[$sType]['need']}"));
                if(!$oQuestionExist)
                {
                    $this->addResult("Question {$sCode}{$sType} need a {$sCode}{$aDelphiCodes[$sType]['need']}");
                    return false;
                }
            }
            $iOrder=$oQuestionBase->question_order;
            // Find order
            foreach($aDelphiKeys as $sDelphiKey)
            {
                if($sType==$sDelphiKey)
                    break;
                $oQuestionOrder=Question::model()->find("sid=:sid AND gid=:gid AND language=:language AND title=:title",array(":sid"=>$this->iSurveyId,":gid"=>$iGid,":language"=>$this->sLanguage,":title"=>"{$sCode}{$sDelphiKey}"));
                if($oQuestionOrder)
                    $iOrder=$oQuestionOrder->question_order;
            }
            if($iNewQid=$this->createQuestion($sCode,$sType,$iGid,$iOrder))
            {
                $oSurvey=Survey::model()->findByPk($this->iSurveyId);
                $aLangs=$oSurvey->getAllLanguages();
                if(isset($aDelphiCodes[$sType]['hidden']))
                    $this->setQuestionHidden($iNewQid);
                if(isset($aDelphiCodes[$sType]['condition']))
                    $this->setQuestionCondition($iNewQid,$sType,$sCode);
            }
        }
        if($sAction=='update' || $sAction=='createupdate')
        {
            $oQuestion=Question::model()->find("sid=:sid AND language=:language AND title=:title",array(":sid"=>$this->iSurveyId,":language"=>$this->sLanguage,":title"=>"{$sCode}{$sType}"));

            if(!$oQuestion)
            {
                $this->addResult("Question with code {$sCode}{$sType} don't exist in your survey",'error');
                return false;
            }
            $oSurvey=Survey::model()->findByPk($this->iSurveyId);
            $aLangs=$oSurvey->getAllLanguages();
            // We have the id
            switch ($sType)
            {
                case 'res':
                    if(isset($aScores[$iQid]['score']))
                    {
                        $iCount=Question::model()->updateAll(array('question'=>$aScores[$iQid]['score']),"sid=:sid AND qid=:qid",array(":sid"=>$this->iSurveyId,":qid"=>$oQuestion->qid));
                        $this->addResult("{$oQuestion->title} updated width {$aScores[$iQid]['score']}",'success');
                    }
                    else
                    {
                        $iCount=Question::model()->updateAll(array('question'=>""),"sid=:sid AND qid=:qid",array(":sid"=>$this->iSurveyId,":qid"=>$oQuestion->qid));
                        $this->addResult("{$oQuestion->title}  not updated width score : unable to find score",'warning');
                    }
                    break;
                case 'hist':
                    foreach($aLangs as $sLang)
                    {
                        $oQuestionBase=Question::model()->find("sid=:sid AND qid=:qid AND language=:language",array(":sid"=>$this->iSurveyId,":qid"=>$iQid,":language"=>$sLang));
                        if($oQuestionBase){
                            $newQuestionHelp = "<div class='kce-content'>".$oQuestionBase->question."<div>";
                            Question::model()->updateAll(array('help'=>$newQuestionHelp),"sid=:sid AND title=:title AND language=:language",array(":sid"=>$this->iSurveyId,":title"=>$oQuestionBase->title.$sType,":language"=>$sLang));
                            $this->addResult("{$oQuestionBase->title}{$sType} question help updated",'success');
                        }else{
                            $this->addResult("Unable to find $iQid to update history for language $sLang.",'error');
                        }
                    }
                    break;
                case 'comm':
                    break;
                case 'comh':
                    // Find the old comm value
                    $oQuestionExist=Question::model()->find("sid=:sid AND language=:language AND title=:title",array(":sid"=>$this->iSurveyId,":language"=>$this->sLanguage,":title"=>"{$sCode}comm"));
                    if($oQuestionExist && $this->oldSchema)
                    {
                        $sColumnName=$this->getOldField($this->oldSchema,$oQuestionExist->qid);
                        if($sColumnName)
                        {
                            $baseQuestionText =$this->getOldAnswerText($sColumnName->name);
                            foreach($aLangs as $sLang)
                            {
                                $newQuestionHelp="<div class='kce-content'>".$baseQuestionText."</div>";
                                Question::model()->updateAll(array('help'=>$newQuestionHelp),"sid=:sid AND title=:title AND language=:language",array(":sid"=>$this->iSurveyId,":title"=>$oQuestionBase->title.$sType,":language"=>$sLang));
                            }
                            $this->addResult("{$oQuestionBase->title}{$sType} question help updated with list of answer",'success');
                        }
                        else
                        {
                            $newQuestionHelp="";
                            Question::model()->updateAll(array('help'=>$newQuestionText),"sid=:sid AND title=:title",array(":sid"=>$this->iSurveyId,":title"=>$oQuestionBase->title.$sType));
                            $this->addResult("{$oQuestionBase->title}{$sType} question help clear: question was not found in old survey",'warning');
                        }

                    }
                    break;
                case 'cgd':
                    break;
                case 'cgdh':
                    // Find the old cgd value
                    $oQuestionExist=Question::model()->find("sid=:sid AND language=:language AND title=:title",array(":sid"=>$this->iSurveyId,":language"=>$this->sLanguage,":title"=>"{$sCode}cgd"));
                    if($oQuestionExist)
                    {
                        $sColumnName=$this->getOldField($oldSchema,$oQuestionExist->qid);
                        if($sColumnName)
                        {
                            $baseQuestionText =$this->getOldAnswerText($sColumnName->name);
                            foreach($aLangs as $sLang)
                            {
                                $newQuestionText = "<div class='kce-accordion'>";
                                $newQuestionText .= "<p class='kce-title comment-title'>".$this->get("commentalthist_{$sLang}", 'Survey', $this->iSurveyId,$this->get('commentalthist',null,null,$this->settings['commentalthist']['default']))."</p><div class='kce-content'>".$baseQuestionText."</div></div>";
                                Question::model()->updateAll(array('question'=>$newQuestionText),"sid=:sid AND title=:title AND language=:language",array(":sid"=>$this->iSurveyId,":title"=>$oQuestionBase->title.$sType,":language"=>$sLang));
                            }
                            $this->addResult("{$oQuestionBase->title}{$sType} question text updated with list of answer",'success');
                        }
                        else
                        {
                            $newQuestionText="";
                            Question::model()->updateAll(array('question'=>$newQuestionText),"sid=:sid AND title=:title",array(":sid"=>$this->iSurveyId,":title"=>$oQuestionBase->title.$sType));
                            $this->addResult("{$oQuestionBase->title}{$sType} question text clear: question was not found in old survey",'warning');
                        }
                    }
                    break;
                default:
                    break;
            }
        }
    }
    private function doCommentQuestion($iQid,$sAction)
    {
        $oSurvey=Survey::model()->findByPk($this->iSurveyId);
        $aLangs=$oSurvey->getAllLanguages();
        $oQuestionBase=Question::model()->find("sid=:sid AND language=:language AND qid=:qid",array(":sid"=>$this->iSurveyId,":language"=>$this->sLanguage,":qid"=>"{$iQid}"));
        if(!$oQuestionBase)
        {
            $this->addResult("No question {$iQid} in survey",'error');
            return false;
        }
        $sCode=$oQuestionBase->title;
        $oQuestion=Question::model()->find("sid=:sid AND language=:language AND title=:title",array(":sid"=>$this->iSurveyId,":language"=>$this->sLanguage,":title"=>"{$sCode}h"));
        if($oQuestion)
            $oHidden=QuestionAttribute::model()->find("qid=:qid AND attribute='hidden'",array(":qid"=>$oQuestion->qid));
        $bHidden=( isset($oHidden) && $oHidden->value)? true : false;
        switch($sAction)
        {
            case 'none':
                if($oQuestion)
                    $this->addResult("{$oQuestion->title} exist in survey.",'warning');
                return;
            case 'hide':
                if($oQuestion && !$bHidden)
                {
                    if($this->setQuestionHidden($oQuestion->qid))
                        $this->aResult['success'][]="{$oQuestion->title} was hide to respondant";
                    else
                        $this->aResult['warning'][]="{$oQuestion->title} unable to hide to respondant";
                }
                return;
            case 'create':
                //Existing question
                if($oQuestion)
                {
                    $this->addResult("A question with code {$sCode}h already exist in your survey, can not create a new one",'error');
                    return false;
                }
                $iOrder=$oQuestionBase->question_order;
                if($iNewQid=$this->createQuestion($oQuestionBase->title,"h",$oQuestionBase->gid,$iOrder))
                {
                    $oQuestion=Question::model()->find("sid=:sid AND language=:language AND qid=:qid",array(":sid"=>$this->iSurveyId,":language"=>$this->sLanguage,":qid"=>$iNewQid));
                }
                else
                {
                    break;
                }
            case 'update':
                if($oQuestion)
                {
                    if($bHidden)
                    {
                        if($this->setQuestionShown($oQuestion->qid))
                            $this->aResult['success'][]="{$oQuestion->title} was shown to respondant";
                        else
                            $this->aResult['warning'][]="{$oQuestion->title} unable to shown to respondant";
                    }
                    if($oQuestionBase && $this->oldSchema)
                    {
                        $sColumnName=$this->getOldField($this->oldSchema,$oQuestionBase->qid);
                        if($sColumnName)
                        {
                            $baseQuestionText =$this->getOldAnswerText($sColumnName->name);
                            foreach($aLangs as $sLang)
                            {
                                $newQuestionHelp="<div class='kce-content'>".$baseQuestionText."</div>";
                                Question::model()->updateAll(array('help'=>$newQuestionHelp),"sid=:sid AND qid=:qid AND language=:language",array(":sid"=>$this->iSurveyId,":qid"=>$oQuestion->qid,":language"=>$sLang));
                            }
                            $this->addResult("{$oQuestionBase->title}h question help updated with list of answer",'success');
                        }
                        else
                        {
                            $newQuestionHelp="";
                            Question::model()->updateAll(array('help'=>$newQuestionText),"sid=:sid AND qid=:qid",array(":sid"=>$this->iSurveyId,":qid"=>$oQuestion->qid));
                            $this->addResult("{$oQuestionBase->title}h question help clear: question was not found in old survey",'warning');
                        }
                    }
                }
                break;
            case 'show':
                if($bHidden)
                {
                    if($this->setQuestionShown($oQuestion->qid))
                        $this->aResult['success'][]="{$oQuestion->title} was shown to respondant";
                    else
                        $this->aResult['warning'][]="{$oQuestion->title} unable to shown to respondant";
                }
                return;
            default:
                $this->addResult("Unknow action {$sDo} {$iQid} {$sType} {$sAction} in survey",'warning');
                return false;
        }
    }
    private function createQuestion($sCode,$sType,$iGid,$iOrder)
    {
        //Need to renumber all questions on or after this
        $iOrder++;
        $sQuery = "UPDATE {{questions}} SET question_order=question_order+1 WHERE sid=:sid AND gid=:gid AND question_order >= :order";
        Yii::app()->db->createCommand($sQuery)->bindValues(array(':sid'=>$this->iSurveyId,':gid'=>$iGid, ':order'=>$iOrder))->query();
        if($sType=="h")
        {
            $sNewQuestionType="X";
        }
        else
        {
            $sNewQuestionType=$this->aDelphiCodes[$sType]['questiontype'];
        }
        switch ($sType)
        {
            case 'hist':
                $newQuestionText = "<p class='kce-default'>".$this->get("historytext_{$this->sLanguage}", 'Survey', $this->iSurveyId,$this->get('historytext',null,null,$this->settings['historytext']['default']))."</p>";
                break;
            case 'comm':
                $newQuestionText = $this->get("commenttext_{$this->sLanguage}", 'Survey', $this->iSurveyId,$this->get('commenttext',null,null,$this->settings['commenttext']['default']));
                break;
            case 'comh':
                $oQuestionComment=Question::model()->find("sid=:sid and title=:title and language=:language",array(":sid"=>$this->iSurveyId,":title"=>$sCode."comm",":language"=>$this->sLanguage));
                $newQuestionText = "<p class='kce-default'>".$this->get("commenthist_{$this->sLanguage}", 'Survey', $this->iSurveyId,$this->get('commenthist',null,null,$this->settings['commenthist']['default']))."</p>";
                if(isset($oQuestionComment) && $oQuestionComment->question)
                    $newQuestionText .= "<div class='kce-historycomment'>".$oQuestionComment->question."</div>";
                break;
            case "h":
                $oQuestionComment=Question::model()->find("sid=:sid and title=:title and language=:language",array(":sid"=>$this->iSurveyId,":title"=>$sCode,":language"=>$this->sLanguage));
                $newQuestionText = "<p class='kce-default'>".$this->get("commenthist_{$this->sLanguage}", 'Survey', $this->iSurveyId,$this->get('commenthist',null,null,$this->settings['commenthist']['default']))."</p>";
                if(isset($oQuestionComment) && $oQuestionComment->question)
                    $newQuestionText .= "<div class='kce-historycomment'>".$oQuestionComment->question."</div>";
                break;
            default:
                $newQuestionText="";
                break;
        }
        $oQuestion= new Question;
        $oQuestion->sid = $this->iSurveyId;
        $oQuestion->gid = $iGid;
        $oQuestion->title = $sCode.$sType;
        $oQuestion->question = $newQuestionText;
        $oQuestion->help = '';
        $oQuestion->preg = '';
        $oQuestion->other = 'N';
        $oQuestion->mandatory = 'N';
        
        $oQuestion->type=$sNewQuestionType;
        $oQuestion->question_order = $iOrder;
        $oSurvey=Survey::model()->findByPk($this->iSurveyId);
        $oQuestion->language = $oSurvey->language;
        if($oQuestion->save())
        {
            $iQuestionId=$oQuestion->qid;
            $aLang=$oSurvey->additionalLanguages;
            foreach($aLang as $sLang)
            {
                switch ($sType)
                {
                    case 'hist':
                        $newQuestionText = "<p class='kce-default'>".$this->get("historytext_{$sLang}", 'Survey', $this->iSurveyId,$this->get('historytext',null,null,$this->settings['historytext']['default']))."</p>";
                        break;
                    case 'comm':
                        $newQuestionText = $this->get("commenttext_{$sLang}", 'Survey', $this->iSurveyId,$this->get('commenttext',null,null,$this->settings['commenttext']['default']));
                        break;
                    case 'commh':
                        $oQuestionComment=Question::model()->find("sid=:sid and title=:title and language=:language",array(":sid"=>$this->iSurveyId,":title"=>$sCode."comm",":language"=>$sLang));
                        $newQuestionText = "<p class='kce-default'>".$this->get("commenthist_{$sLang}", 'Survey', $this->iSurveyId,$this->get('commenthist',null,null,$this->settings['commenthist']['default']))."</p>";
                        if(isset($oQuestionComment) && $oQuestionComment->question)
                            $newQuestionText .= "<div class='kce-historycomment'>".$oQuestionComment->question."</div>";
                        break;
                    case 'h':
                        $oQuestionComment=Question::model()->find("sid=:sid and title=:title and language=:language",array(":sid"=>$this->iSurveyId,":title"=>$sCode,":language"=>$sLang));
                        $newQuestionText = "<p class='kce-default'>".$this->get("commenthist_{$sLang}", 'Survey', $this->iSurveyId,$this->get('commenthist',null,null,$this->settings['commenthist']['default']))."</p>";
                        if(isset($oQuestionComment) && $oQuestionComment->question)
                            $newQuestionText .= "<div class='kce-historycomment'>".$oQuestionComment->question."</div>";
                        break;
                    default:
                        $newQuestionText="";
                        break;
                }
                $oLangQuestion= new Question;
                $oLangQuestion->sid = $this->iSurveyId;
                $oLangQuestion->gid = $iGid;
                $oLangQuestion->qid = $iQuestionId;
                $oLangQuestion->title = $sCode.$sType;
                $oLangQuestion->question = $newQuestionText;
                $oLangQuestion->help = $newQuestionText;
                $oLangQuestion->type = $oQuestion->type;
                $oLangQuestion->question_order = $iOrder;
                $oLangQuestion->language = $sLang;
                if(!$oLangQuestion->save())
                    tracevar($oLangQuestion->getErrors());
            }

            $this->addResult("Created question {$sCode}{$sType}.",'success');
            return $iQuestionId;
        }
        $this->addResult("Unable to create question {$sCode}{$sType}, please contact the software developer.",'error',$oQuestion->getErrors());
    }
    private function setQuestionHidden($iQid)
    {
        $oQuestion=Question::model()->find("sid=:sid AND qid=:qid",array(":sid"=>$this->iSurveyId,":qid"=>$iQid));
        if(!$oQuestion)
            return;
        $oAttribute=QuestionAttribute::model()->find("qid=:qid AND attribute='hidden'",array(":qid"=>$iQid));
        if(!$oAttribute)
        {
            $oAttribute=new QuestionAttribute;
            $oAttribute->qid=$iQid;
            $oAttribute->attribute="hidden";
        }
        $oAttribute->value=1;
        if($oAttribute->save())
        {
            return true;
        }
        else
            $this->addResult("Unable to set {$iQid} hidden",'error',$oAttribute->getErrors());
    }
    private function setQuestionShown($iQid)
    {
        $oQuestion=Question::model()->find("sid=:sid AND qid=:qid",array(":sid"=>$this->iSurveyId,":qid"=>$iQid));
        if(!$oQuestion)
            return;
        $iAttribute=QuestionAttribute::model()->deleteAll("qid=:qid AND attribute='hidden'",array(":qid"=>$iQid));
        if($iAttribute)
        {
           return true;
        }
        return false;
    }
    private function setQuestionCondition($iQid,$sType,$sCode="")
    {
        $sCondition=isset($this->aDelphiCodes[$sType]['condition'])?$this->aDelphiCodes[$sType]['condition']:false;
        if($sCondition)
        {
            $oQuestion=Question::model()->find("sid=:sid AND language=:language AND title=:title",array(":sid"=>$this->iSurveyId,":language"=>$this->sLanguage,":title"=>"{$sCode}{$sType}"));
            if($oQuestion)
            {
                $sCondition=str_replace("{QCODE}",$sCode,$sCondition);
                $updatedCount=Question::model()->updateAll(array('relevance'=>$sCondition),"sid=:sid AND qid=:qid",array(":sid"=>$this->iSurveyId,":qid"=>$iQid));
                return $updatedCount;
            }
            else
            {
                $this->addResult("Unable to find {$iQid} to set condition",'error');
            }
        }
    }
    /**
    * @param $iQid : base question qid
    * @param $sCode : base question title
    * @param $sType : new question type
    */
    private function getCheckQuestionSettings($iQid,$sCode,$sType)
    {
        $aQuestionsSettings=array();
        $oQuestionResult=Question::model()->find("sid=:sid AND language=:language AND title=:title",array(":sid"=>$this->iSurveyId,":language"=>$this->sLanguage,":title"=>"{$sCode}{$sType}"));
        if($oQuestionResult)
        {
            $aQuestionsSettings["q[{$iQid}][{$sType}][view]"]['type']='info';
            $aQuestionsSettings["q[{$iQid}][{$sType}][view]"]['content']=$this->aLang[$sType]['view'];
        }
        elseif(!$this->bSurveyActivated && isset($this->aDelphiCodes[$sType]['create']))
        {
            $aQuestionsSettings["q[{$iQid}][{$sType}][create]"]['type']='checkbox';
            $aQuestionsSettings["q[{$iQid}][{$sType}][create]"]['label']=$this->aLang[$sType]['create'];
            $aQuestionsSettings["q[{$iQid}][{$sType}][create]"]['current']=$this->aDelphiCodes[$sType]['create'];
        }
        return $aQuestionsSettings;
    }
    private function getValidateQuestionSettings($oQuestion)
    {
        $oldSchema=$this->oldSchema;
        $aQuestionsSettings=array();
        $sFieldName=$oQuestion->sid."X".$oQuestion->gid."X".$oQuestion->qid;
        $aQuestionsInfo[$oQuestion->qid]=array();
        if($aQuestionsInfo[$oQuestion->qid]['oldField']=$this->getOldField($oldSchema,$oQuestion->qid))
        {

            // Test if question is hidden (already validated)
            $oAttributeHidden=QuestionAttribute::model()->find("qid=:qid AND attribute='hidden'",array(":qid"=>$oQuestion->qid));
            $sQuestionTextTitle=str_replace("'",'’',FlattenText($oQuestion->question));
            $sQuestionText=ellipsize($sQuestionTextTitle,80);
            if($oQuestion->title!==preg_replace("/[^_a-zA-Z0-9]/", "", $oQuestion->title))
            {
                $aQuestionsSettings["q_{$oQuestion->qid}"]['type']='info';
                $aQuestionsSettings["q_{$oQuestion->qid}"]['content']=CHtml::tag('div',array('class'=>'questiontitle','title'=>$sQuestionTextTitle),"<strong>Invalid title : {$oQuestion->title}</strong> : LimeSurvey 2.05 title allow only alphanumeric (no space, no dot ..)");
            }
            else
            {
                // Get the % and evaluate note
                $aOldAnswers=$this->getOldAnswersInfo($oQuestion->qid,$oQuestion->type,$aQuestionsInfo[$oQuestion->qid]['oldField']->name);
                $iTotal = 0;
                $iTotalValue=0;
                $iTotalNeg=0;
                $iTotalPos=0;

                foreach ($aOldAnswers as $aOldAnswer) {
                    $iTotal += $aOldAnswer['count'];
                    if($aOldAnswer['assessment_value'])
                        $iTotalValue += $aOldAnswer['count'];
                    if(intval($aOldAnswer['assessment_value'])<0)
                        $iTotalNeg += $aOldAnswer['count'];
                    if(intval($aOldAnswer['assessment_value'])>0)
                        $iTotalPos += $aOldAnswer['count'];
                }
                $sHtmlTable="";
                $hiddenPart="";
                $bValidate=false;
                $iScore=0;

                if($iTotalValue)
                {
                    $sHtmlTable.="<table class='kce-table clearfix table table-striped table-bordered'><thead><td></td><th>count</th><th>%</th></thead><tbody>";
                    $iTotalPosPC=number_format($iTotalPos/$iTotalValue*100)."%";
                    $iTotalNegPC=number_format($iTotalNeg/$iTotalValue*100)."%";
                    $sHtmlTable.="<tr><th>Upper than 0</th><td>{$iTotalPos}</td><td>{$iTotalPosPC}</td></tr>";
                    $sHtmlTable.="<tr><th>Lesser than 0</th><td>{$iTotalNeg}</td><td>{$iTotalNegPC}</td></tr>";
                    $sHtmlTable.="</tbody></table>";
                }
                $sHtmlTable.="<table class='kce-table clearfix table table-striped table-bordered'><thead><td></td><th>count</th><th>%</th>";
                $sHtmlTable.="<th>% cumulative</th>";
                if($iTotalValue)
                {
                    $sHtmlTable.="<th>% with value</th>";
                    $sHtmlTable.="<th>% cumulative with value</th>";
                }
                $sHtmlTable.="</thead><tbody>";
                $cumulBrut=0;
                $cumulValue=0;
                foreach ($aOldAnswers as $sCode=>$aOldAnswer)
                {
                    $sHtmlTable.="<tr>";
                    $sHtmlTable.="<th title='".str_replace("'",'’',FlattenText($aOldAnswer['answer']))."'>{$sCode} : <small>".ellipsize(FlattenText($aOldAnswer['answer']),60)."</small></th>";
                    $sHtmlTable.="<td>{$aOldAnswer['count']}</td>";
                    if($iTotal>0)
                    {
                        $sHtmlTable.="<td>".number_format($aOldAnswer['count']/$iTotal*100)."%"."</td>";
                        $cumulBrut+=$aOldAnswer['count'];
                        $sHtmlTable.="<td>".number_format($cumulBrut/$iTotal*100)."%"."</td>";
                    }
                    else
                        $sHtmlTable.="<td>/</td><td>/</td>";
                    if($iTotalValue>0)
                    {
                        if(intval($aOldAnswer['assessment_value'])!=0)
                        {
                            $sHtmlTable.="<td>".number_format($aOldAnswer['count']/$iTotalValue*100)."%"."</td>";
                            $cumulValue+=$aOldAnswer['count'];
                            $sHtmlTable.="<td>".number_format($cumulValue/$iTotalValue*100)."%"."</td>";
                        }else{
                            $sHtmlTable.="<td>/</td><td>/</td>";
                        }
                    }
                    $sHtmlTable.="</tr>";
                }
                $sHtmlTable.="</tbody></table>";

                    //~ $aHtmlListQuestion.="<dt title='".str_replace("'",'’',FlattenText($aOldAnswer['answer']))."'>{$sCode} : <small>".ellipsize(FlattenText($aOldAnswer['answer']),60)."</small></dt>"
                                    //~ ."<dd>".$aOldAnswer['count']." : ";
//~ 
                    //~ if($iTotal>0)
                    //~ {
                        //~ $aHtmlListQuestion.=number_format($aOldAnswer['count']/$iTotal*100)."%";
                        //~ $hiddenPart.=CHtml::hiddenField("value[{$oQuestion->qid}][{$sCode}]",$aOldAnswer['count']/$iTotal);
                    //~ }
                    //~ $aHtmlListQuestion.="</dd>";
                    //~ $iScore+=$aOldAnswer['count']*$aOldAnswer['assessment_value'];
                    //~ $aHtmlListQuestions[]=$aHtmlListQuestion;
                //~ }


                $aQuestionsSettings["q_{$oQuestion->qid}"]['type']='info';
                $aQuestionsSettings["q_{$oQuestion->qid}"]['content']="<div class='questiontitle' title='{$sQuestionTextTitle}'><strong class='label label-info'>{$oQuestion->title}</strong> : {$sQuestionText}</div><div class='oldresult  clearfix'>"
                    .$sHtmlTable
                    ."</div>"
                    .$hiddenPart;

                $aQuestionsSettings["validate[{$oQuestion->qid}]"]['type']='select';
                $aQuestionsSettings["validate[{$oQuestion->qid}]"]['label']="Validation of this question";
                $aQuestionsSettings["validate[{$oQuestion->qid}]"]['options']=array(
                    'hide'=>"Hide it to respondant",
                    'show'=>"Show it to respondant",
                );
                $aQuestionsSettings["validate[{$oQuestion->qid}]"]['current']=($oAttributeHidden && $oAttributeHidden->value) ? 'hide' : 'show';

                foreach($this->aDelphiCodes as $sDelphiCode=>$aSettings)
                {
                    $aQuestionsSettings=array_merge($aQuestionsSettings,$this->getComplementValidateQuestionSettings($oQuestion->qid,$oQuestion->title,$sDelphiCode));
                }
            }
        }
        else
        {
            $oAttributeHidden=QuestionAttribute::model()->find("qid=:qid AND attribute='hidden'",array(":qid"=>$oQuestion->qid));
            $sQuestionTextTitle=str_replace("'",'’',FlattenText($oQuestion->question));
            $sQuestionText=ellipsize($sQuestionTextTitle,80);
            $aQuestionsSettings["q_".$oQuestion->qid]['type']='info';
            $aQuestionsSettings["q_".$oQuestion->qid]['content']="<strong class='questiontitle' title='{$sQuestionTextTitle}'>{$oQuestion->title}</strong> : no corresponding question";
        }
        return $aQuestionsSettings;
    }
    /**
    * @param $iQid : base question qid
    * @param $sCode : base question title
    * @param $sType : new question type
    */
    private function getComplementValidateQuestionSettings($iQid,$sCode,$sType,$sValue=NULL)
    {
        $aQuestionsSettings=array();
        $aDelphiCodes=$this->aDelphiCodes;
        $oQuestionResult=Question::model()->find("sid=:sid AND language=:language AND title=:title",array(":sid"=>$this->iSurveyId,":language"=>$this->sLanguage,":title"=>"{$sCode}{$sType}"));
        if(isset($this->aDelphiCodes[$sType]['select']))
        {
            $sLabel="<span class='label'>{$sCode}{$sType}</span>".$this->aDelphiCodes[$sType]['select']['label'];
            // Add list of comment 
            if($sType=="comh")
            {
                // Find the old comm value
                $oQuestionExist=Question::model()->find("sid=:sid AND language=:language AND title=:title",array(":sid"=>$this->iSurveyId,":language"=>$this->sLanguage,":title"=>"{$sCode}comm"));
                if($oQuestionExist && $this->oldSchema)
                {
                    $sColumnName=$this->getOldField($this->oldSchema,$oQuestionExist->qid);
                    if($sColumnName)
                    {
                        $baseQuestionText =$this->getOldAnswerText($sColumnName->name);
                        if($baseQuestionText){
                            $jsonBaseQuestionText=json_encode($baseQuestionText);
                            $sLabel="<div class='kcetitle'>$baseQuestionText</div><span class='label' data-kcetitle='true'>See previous comments</span> {$sLabel}";
                        }else
                            $sLabel="<span class='label label-warning'>No old answers</span> {$sLabel}";
                    }
                    else
                        $sLabel="<span class='label label-warning'>No old answers</span> {$sLabel}";
                }
                else
                    $sLabel="<span class='label label-warning'>No old question</span> {$sLabel}";
            }

            $aQuestionsSettings["q[{$iQid}][{$sType}][select]"]['type']='select';
            $aQuestionsSettings["q[{$iQid}][{$sType}][select]"]['label']=$sLabel;
            $aOptions=$this->aDelphiCodes[$sType]['select']['options'];
            if($oQuestionResult)
            {
                unset($aOptions['none']);
                unset($aOptions['create']);
                if(QuestionAttribute::model()->find("qid=:qid AND attribute='hidden'",array(":qid"=>$oQuestionResult->qid)))
                    $aQuestionsSettings["q[{$iQid}][{$sType}][select]"]['current']='hide';
                else
                    $aQuestionsSettings["q[{$iQid}][{$sType}][select]"]['current']='show';
            }
            else
            {
                unset($aOptions['hide']);
                unset($aOptions['update']);
                unset($aOptions['show']);
                $aQuestionsSettings["q[{$iQid}][{$sType}][select]"]['current']='none';

            }
            if(count($aOptions))
                $aQuestionsSettings["q[{$iQid}][{$sType}][select]"]['options']=$aOptions;
            else
                unset($aQuestionsSettings["q[{$iQid}][{$sType}][select]"]);
        }
        else
        {
            if($oQuestionResult)
            {
                if(isset($this->aDelphiCodes[$sType]['update']))
                {
                    $aQuestionsSettings["q[{$iQid}][{$sType}][update]"]['type']='checkbox';
                    $aQuestionsSettings["q[{$iQid}][{$sType}][update]"]['label']=$this->aLang[$sType]['update'];
                    $aQuestionsSettings["q[{$iQid}][{$sType}][update]"]['current']=$this->aDelphiCodes[$sType]['update'];
                }
                else
                {
                    $aQuestionsSettings["q[{$iQid}][{$sType}][view]"]['type']='info';
                    $aQuestionsSettings["q[{$iQid}][{$sType}][view]"]['content']=$this->aLang[$sType]['view'];
                }
            }
            elseif(!$this->bSurveyActivated && isset($aDelphiCodes[$sType]['createupdate']))
            {
                // Default current
                $bCurrent=$this->aDelphiCodes[$sType]['createupdate'];
                if(isset($aDelphiCodes[$sType]['need']))
                {
                    $oQuestionExist=Question::model()->find("sid=:sid AND language=:language AND title=:title",array(":sid"=>$this->iSurveyId,":language"=>$this->sLanguage,":title"=>"{$sCode}{$aDelphiCodes[$sType]['need']}"));
                    
                    $bCurrent=$this->aDelphiCodes[$sType]['createupdate'] && (bool)$oQuestionExist;
                }
                $aQuestionsSettings["q[{$iQid}][{$sType}][createupdate]"]['type']='checkbox';
                $aQuestionsSettings["q[{$iQid}][{$sType}][createupdate]"]['label']=$this->aLang[$sType]['createupdate'];
                $aQuestionsSettings["q[{$iQid}][{$sType}][createupdate]"]['current']=$bCurrent;
            }
        }
        return $aQuestionsSettings;
    }
    private function getCommentQuestionSettings($oQuestion)
    {
        $oldSchema=$this->oldSchema;
        $aQuestionsSettings=array();
        $sFieldName=$oQuestion->sid."X".$oQuestion->gid."X".$oQuestion->qid;
        $aQuestionsInfo[$oQuestion->qid]=array();
        $oAttributeHidden=QuestionAttribute::model()->find("qid=:qid AND attribute='hidden'",array(":qid"=>$oQuestion->qid));
        $sQuestionTextTitle=str_replace("'",'’',FlattenText($oQuestion->question));
        $sQuestionText=ellipsize($sQuestionTextTitle,50);
        if($oQuestion->title!==preg_replace("/[^_a-zA-Z0-9]/", "", $oQuestion->title))
        {
            $aQuestionsSettings["q_{$oQuestion->qid}"]['type']='info';
            $aQuestionsSettings["q_{$oQuestion->qid}"]['content']=CHtml::tag('div',array('class'=>'questiontitle','title'=>$sQuestionTextTitle),"<strong>Invalid title : {$oQuestion->title}</strong> : LimeSurvey 2.05 title allow only alphanumeric (no space, no dot ..)");
        }
        else
        {
            $aQuestionsSettings["validate[{$oQuestion->qid}]"]['type']='select';
            $aQuestionsSettings["validate[{$oQuestion->qid}]"]['label']="<div class='' title='{$sQuestionTextTitle}'><span class='label'>{$oQuestion->title}</span> : {$sQuestionText}</div>";
            $aQuestionsSettings["validate[{$oQuestion->qid}]"]['options']=array(
                'hide'=>"Hide it to respondant",
                'show'=>"Show it to respondant",
            );
            $aQuestionsSettings["validate[{$oQuestion->qid}]"]['current']=($oAttributeHidden && $oAttributeHidden->value) ? 'hide' : 'show';
            // Adding history question
            
        }
        if($aQuestionsInfo[$oQuestion->qid]['oldField']=$this->getOldField($oldSchema,$oQuestion->qid))
        {
            $sColumnName=$this->getOldField($this->oldSchema,$oQuestion->qid);
            if($sColumnName)
            {
                $oldAnswerText =$this->getOldAnswerText($sColumnName->name);
            }else{
                $oldAnswerText =null;
            }
            // Do label
            if($oldAnswerText)
            {
                $sLabel="<span class='label'>{$oQuestion->title}h</span> <div class='kcetitle'>$oldAnswerText</div><span class='label label-inverse' data-kcetitle='true'>See</span> comments from the previous round (automatic)";
            }
            else
            {
                $sLabel="<span class='label'>{$oQuestion->title}h</span>No comments from the previous round (automatic)";
            }
            // Adding history question only if we have old field
            // Find if history exist
            $oHistoryQuestion=Question::model()->find("sid=:sid AND language=:language AND title=:title",array(":sid"=>$this->iSurveyId,":language"=>$this->sLanguage,":title"=>"{$oQuestion->title}h"));
            $aSettings=array(
                'type'=>"select",
                'label'=>$sLabel,
                'options'=>array(
                    'none'=>'No creation of this question',
                    'hide'=>'Hide this question',
                    'create'=>"Create question with the new comment list (and Show it)",
                    'update'=>"Update question with the new comment list (and Show it)",
                    "show"=>"Show the question",
                ),
            );
            if($oHistoryQuestion)
            {

                $oAttributeHidden=QuestionAttribute::model()->find("qid=:qid AND attribute='hidden'",array(":qid"=>$oHistoryQuestion->qid));
                unset($aSettings['options']['none']);
                unset($aSettings['options']['create']);
                $aSettings['current']=($oAttributeHidden && $oAttributeHidden->value) ? 'hide' : 'show';
            }
            else
            {
                unset($aSettings['options']['hide']);
                unset($aSettings['options']['update']);
                unset($aSettings['options']['show']);
                $aSettings['current']='none';
            }
            $aQuestionsSettings["commhist[{$oQuestion->qid}]"]=$aSettings;
        }

        return $aQuestionsSettings;
    }
    private function htmlListFromQueryAll($aStrings, $htmlOptions=array())
    {
        $sHtmlList=array();
        if(!empty($aStrings))
        {
            foreach($aStrings as $aString)
            {
#                if(is_string($aString))
#                    $sHtmlList[]=CHtml::tag("li",array(),$aString);
#                else
                    $sHtmlList[]=CHtml::tag("li",array(),current($aString));
            }
            return Chtml::tag("ul",array('class'=>'kce-answerstext'),implode($sHtmlList,"\n"));
        }
    }
    // Manage return $aResult
    private function addResult($sString,$sType='warning',$oTrace=NULL)
    {
        if(in_array($sType,array('success','warning','error')) && is_string($sString) && $sString)
        {
            $this->aResult[$sType][]=$sString;
        }
        elseif(is_numeric($sType))
        {
            $this->aResult['question'][]=$sType;
        }
        elseif($sType)
        {
            tracevar(array($sType,$sString));
        }
        if($oTrace)
            tracevar($oTrace);
    }
}

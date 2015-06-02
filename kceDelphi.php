<?php
/**
 * kceDelphi Plugin for LimeSurvey
 * A simplified Delphi method for KCE
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2014 Denis Chenu <http://sondages.pro>
 * @copyright 2014 Belgian Health Care Knowledge Centre (KCE) <http://kce.fgov.be>
 * @license GPL v3
 * @version 1.0
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
    static protected $description = 'Activate the Delphi method for Delphi - v2.0.2';

    private $iSurveyId=false;
    private $bSurveyActivated=false;
    private $sTableName="";
    private $sError="Unknow error.";
    private $sLanguage="";
    private static $aValidQuestion=array("!","L","O");

    private $aResult=array('success'=>array(),'warning'=>array(),'error'=>array());
    private $validatescore,$scoreforyes,$scoreforno;

    private $aDelphiCodes=array(
                            'res'=>array(
                                'create'=>false,
                                'createupdate'=>false,
                                'update'=>true,
                                'questiontype'=>"*",
                                'hidden'=>true,
                            ),
                            'hist'=>array(
                                'create'=>false,
                                'createupdate'=>true,
                                'update'=>false,
                                'questiontype'=>"X",
                            ),
                            'comm'=>array(
                                'create'=>true,
                                'createupdate'=>false,
                                'update'=>null,
                                'questiontype'=>"T",
                                'condition'=>"{QCODE}.value<0",
                                'hidevalidate'=>true,
                            ),
                            'comh'=>array(
                                'create'=>false,
                                'createupdate'=>true,
                                'update'=>true,
                                'questiontype'=>"X",
                                'need'=>"comm",
                                'hidevalidate'=>true,
                            ),
                            'cgd'=>array(
                                'create'=>false,
                                'createupdate'=>false,
                                'update'=>null,
                                'questiontype'=>"T",
                                'condition'=>"{QCODE}.value>0",
                                'hidevalidate'=>true,
                            ),
                            'cgdh'=>array(
                                'create'=>false,
                                'createupdate'=>true,
                                'update'=>true,
                                'questiontype'=>"X",
                                'need'=>"cgd",
                                'hidevalidate'=>true,
                            ),
                        );
    private $aLang=array(
            'res'=>array(
                'view'=>"Have a result question to have average of score",
                'create'=>"Create an empty question to have the average of score",
                'createupdate'=>"Create a result question with the score",
                'update'=>"Update the result question with the average of score",
                ),
            'hist'=>array(
                'view'=>"Have a history question",
                'create'=>"Create an empty history question",
                'createupdate'=>"Create an history question with actual question text",
                'update'=>"Update history with <strong>actual</strong> question text",
                ),
            'comm'=>array(
                'view'=>"Have a question for comment (Disagree)",
                'create'=>"Create a question to enter comment (when disagree : with condition : assesment of question is under 0 (score))",
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
        'validatescore'=>array(
            'type'=>'float',
            'label'=>'Minimal score for a proposal to be validated',
            'default'=>0,
        ),
        'commenttext'=>array(
            'type'=>'string',
            'label'=>'Default question text for comments',
            'default'=>'Can you explain why you disagree with this proposal.',
        ),
        'commentalttext'=>array(
            'type'=>'string',
            'label'=>'Default question text for alternative comments',
            'default'=>'Can you explain why you agree with this proposal.',
        ),
        'historytext'=>array(
            'type'=>'string',
            'label'=>'Default text before history',
            'default'=>'Previous proposal',
        ),
        'commenthist'=>array(
            'type'=>'string',
            'label'=>'Default header for list of comments',
            'default'=>'Previous comments of people who disagree.',
        ),
        'commentalthist'=>array(
            'type'=>'string',
            'label'=>'Default header for list of alternative comments',
            'default'=>'Previous comments of people who agree.',
        ),
    );
    public function __construct(PluginManager $manager, $id) {
        parent::__construct($manager, $id);
        $this->subscribe('beforeSurveySettings');
        $this->subscribe('newSurveySettings');
        //Can call plugin
        $this->subscribe('newDirectRequest');
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
            $aSettings=array(
                    'validatescore'=>array(
                        'type'=>'float',
                        'label'=>'Validate proposal with score up to',
                        'current' => $this->get('validatescore', 'Survey', $oEvent->get('survey'),$this->get('validatescore',null,null,$this->settings['validatescore']['default'])),
                    ),

                );

            // Setting for language
            $oSurvey=Survey::model()->findByPk($iSurveyId);
            $aLangs=$oSurvey->getAllLanguages();
            $sScoreDefault=$this->get('validatescore', 'Survey', $oEvent->get('survey'),$this->get('validatescore',null,null,$this->settings['validatescore']['default']));
            foreach($aLangs as $sLang)
            {
                $aSettings["historytext_{$sLang}"]=array(
                    'type'=>'string',
                    'label'=>"Sentence added before old proposal history ({$sLang})",
                    'current' => $this->get("historytext_{$sLang}", 'Survey', $oEvent->get('survey'),$this->get('historytext',null,null,$this->settings['historytext']['default'])),
                );
            }
            foreach($aLangs as $sLang)
            {
                $aSettings["commenttext_{$sLang}"]=array(
                    'type'=>'string',
                    'label'=>"Sentence for the comments question show if user choose a answer with value less than {$sScoreDefault} ({$sLang})",
                    'current' => $this->get("commenttext_{$sLang}", 'Survey', $oEvent->get('survey'),$this->get('commenttext',null,null,$this->settings['commenttext']['default'])),
                );
            }
            foreach($aLangs as $sLang)
            {
                $aSettings["commentalttext_{$sLang}"]=array(
                    'type'=>'string',
                    'label'=>"Sentence for the comments question show if user choose a answer with value more than {$sScoreDefault} ({$sLang})",
                    'current' => $this->get("commentalttext_{$sLang}", 'Survey', $oEvent->get('survey'),$this->get('commentalttext',null,null,$this->settings['commentalttext']['default'])),
                );
            }
            foreach($aLangs as $sLang)
            {
                $aSettings["commenthist_{$sLang}"]=array(
                    'type'=>'string',
                    'label'=>"Sentence added before comment list (score less than {$sScoreDefault}) ({$sLang})",
                    'current' => $this->get("commenthist_{$sLang}", 'Survey', $oEvent->get('survey'),$this->get('commenthist',null,null,$this->settings['commenthist']['default'])),
                );
            }
            foreach($aLangs as $sLang)
            {
                $aSettings["commentalthist_{$sLang}"]=array(
                    'type'=>'string',
                    'label'=>"Sentence added before comment list (score more than {$sScoreDefault}) ({$sLang})",
                    'current' => $this->get("commentalthist_{$sLang}", 'Survey', $oEvent->get('survey'),$this->get('commentalthist',null,null,$this->settings['commentalthist']['default'])),
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

    $assetUrl = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets');
    Yii::app()->clientScript->registerScriptFile($assetUrl . '/fixfloat.js',CClientScript::POS_END);
    Yii::app()->clientScript->registerCssFile($assetUrl . '/kcedelphi.css');
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
            throw new CHttpException(500);

        $this->iSurveyId=Yii::app()->request->getParam('surveyid');
        $oSurvey=Survey::model()->findByPk($this->iSurveyId);
        if(!$oSurvey)
        {
            throw new CHttpException(400,"Invalid Survey Id." );
        }
        if($oSurvey->active=="Y")
            $this->bSurveyActivated=true;
        // We have survey , test access
        if( !Permission::model()->hasSurveyPermission($this->iSurveyId, 'surveycontent', 'update'))
        {
            Yii::app()->setFlashMessage("Access error : you don't have suffisant rigth to update survey content.",'error');
            App()->controller->redirect(array('admin/survey','sa'=>'view','surveyid'=>$this->iSurveyId));
        }
        $this->validatescore=$this->get('validatescore','Survey',$this->iSurveyId,$oEvent->get('validatescore',$this->settings['validatescore']['default']));
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
            Yii::app()->setFlashMessage("Survey updated, only error is shown");
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
            $sQuestionTextTitle=str_replace("'",'’',FlattenText($oQuestion->question));
            $sQuestionText=ellipsize($sQuestionTextTitle,80);
            if($oAttributeHidden && $oAttributeHidden->value)
            {
                $aQuestionsSettings["q_{$oQuestion->qid}"]['type']='info';
                $aQuestionsSettings["q_{$oQuestion->qid}"]['content']="<div class='questiontitle' title='{$sQuestionTextTitle}'><strong>Validated question {$oQuestion->title}</strong> : {$sQuestionText}</div><!-- <p><small>Do we need to allow delete other question (result, history ....)</small></p> -->";
            }
            else
            {
                $aQuestionsSettings["q_{$oQuestion->qid}"]['type']='info';
                $aQuestionsSettings["q_{$oQuestion->qid}"]['content']="<div class='questiontitle' title='{$sQuestionTextTitle}'><strong>{$oQuestion->title}</strong> : {$sQuestionText}";
                foreach($this->aDelphiCodes as $sDelphiCode=>$aSettings)
                {
                    $aQuestionsSettings=array_merge($aQuestionsSettings,$this->getCheckQuestionSettings($oQuestion->qid,$oQuestion->title,$sDelphiCode));
                }
            }
        }
        $aData['aSettings']=$aQuestionsSettings;
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
            $count = PluginDynamic::model($table)->count();
            $timestamp = date_format(new DateTime(substr($table, -14)), 'Y-m-d H:i:s');
            if($count>0 && !strpos($table,"_timings_"))
                $list[$table]  = "$timestamp ($count responses)";
        }
        $aData['settings']['oldsurveytable'] = array(
            'label' => gT('Source table'),
            'type' => 'select',
            'options' => $list,
        );


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

            $oldTable = PluginDynamic::model($sTableName);
            $oldSchema = $oldTable->getTableSchema();

            // Ok get the information ...
            $oQuestions=$this->getDelphiQuestion();
            $aQuestions=array();
            $aQuestionsSettings=array();
            $aQuestionsInfo=array();

            foreach($oQuestions as $oQuestion){
                $sFieldName=$oQuestion->sid."X".$oQuestion->gid."X".$oQuestion->qid;
                $aQuestionsInfo[$oQuestion->qid]=array();
                if($aQuestionsInfo[$oQuestion->qid]['oldField']=$this->getOldField($oldSchema,$oQuestion->qid))
                {
                    // Test if question is hidden (already validated)
                    $oAttributeHidden=QuestionAttribute::model()->find("qid=:qid AND attribute='hidden'",array(":qid"=>$oQuestion->qid));
                    $sQuestionTextTitle=str_replace("'",'’',FlattenText($oQuestion->question));
                    $sQuestionText=ellipsize($sQuestionTextTitle,80);
                    if($oAttributeHidden && $oAttributeHidden->value)
                    {
                        $aQuestionsSettings["q_{$oQuestion->qid}"]['type']='info';
                        $aQuestionsSettings["q_{$oQuestion->qid}"]['content']="<div class='questiontitle' title='{$sQuestionTextTitle}'><strong>Validated question {$oQuestion->title}</strong> : {$sQuestionText}</div>";
                    }
                    else
                    {
                        // Get the % and evaluate note
                        $aOldAnswers=$this->getOldAnswersInfo($oQuestion->qid,$oQuestion->type,$aQuestionsInfo[$oQuestion->qid]['oldField']->name);
                        $iTotal = 0;
                        foreach ($aOldAnswers as $aOldAnswer) {
                            $iTotal += $aOldAnswer['count'];
                        }
                        $aHtmlListQuestions=array();
                        $hiddenPart="";
                        $bValidate=false;
                        $iScore=0;
                        foreach ($aOldAnswers as $sCode=>$aOldAnswer) {
                            $aHtmlListQuestion="<dt title='".str_replace("'",'’',FlattenText($aOldAnswer['answer']))."'>{$sCode} : <small>".ellipsize(FlattenText($aOldAnswer['answer']),60)."</small></dt>"
                                            ."<dd>".$aOldAnswer['count']." : ";

                            if($iTotal>0)
                            {
                                $aHtmlListQuestion.=number_format($aOldAnswer['count']/$iTotal*100)."%";
                                $hiddenPart.=CHtml::hiddenField("value[{$oQuestion->qid}][{$sCode}]",$aOldAnswer['count']/$iTotal);
                            }
                            $aHtmlListQuestion.="</dd>";
                            $iScore+=$aOldAnswer['count']*$aOldAnswer['assessment_value'];
                            $aHtmlListQuestions[]=$aHtmlListQuestion;
                        }
                        if($iTotal>0)
                        {
                            $aHtmlListQuestions[]="<dt class='score'>Average score</dt><dd class='score'>".number_format($iScore/$iTotal,3)."</dd>";
                            if ($iScore/$iTotal >= $this->validatescore)
                                $bValidate=true ;
                            $hiddenPart.=CHtml::hiddenField("value[{$oQuestion->qid}][score]",$iScore/$iTotal);
                        }

                        $aQuestionsSettings["q_{$oQuestion->qid}"]['type']='info';
                        $aQuestionsSettings["q_{$oQuestion->qid}"]['content']="<div class='questiontitle' title='{$sQuestionTextTitle}'><strong>{$oQuestion->title}</strong> : {$sQuestionText}</div><div class='oldresult'>Old result: <dl>"
                            .implode($aHtmlListQuestions,"\n")
                            ."</dl></div>"
                            .$hiddenPart;

                        $aQuestionsSettings["validate[{$oQuestion->qid}]"]['type']='checkbox';
                        $aQuestionsSettings["validate[{$oQuestion->qid}]"]['label']="Validate this question (Hide it to the respondant)";
                        $aQuestionsSettings["validate[{$oQuestion->qid}]"]['current']=$bValidate;

                        foreach($this->aDelphiCodes as $sDelphiCode=>$aSettings)
                        {
                            $aQuestionsSettings=array_merge($aQuestionsSettings,$this->getValidateQuestionSettings($oQuestion->qid,$oQuestion->title,$sDelphiCode));
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
            }
            $aSurveySettings['oldsurveytable']=array(
                'label' => gT('Source table'),
                'type' => 'select',
                'options' => array($sTableName=>$sTableName),
                'current' => $sTableName,
                'class'=>'hidden'
            );
            $aData['aSettings']=array_merge($aQuestionsSettings,$aSurveySettings);
            $aData['updateUrl']=$this->api->createUrl('plugins/direct', array('plugin' => 'kceDelphi','surveyid'=>$this->iSurveyId, 'function' => 'update'));
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
            $sTableName=$this->sTableName=Yii::app()->request->getPost('oldsurveytable');
            $aTables = App()->getApi()->getOldResponseTables($this->iSurveyId);
            if(!in_array($sTableName,$aTables)){
                Yii::app()->setFlashMessage("Bad table name.",'error');
                App()->controller->redirect($this->api->createUrl('plugins/direct', array('plugin' => 'kceDelphi','surveyid'=>$this->iSurveyId, 'function' => 'view')));
            }

            $oldTable = PluginDynamic::model($sTableName);
            $oldSchema = $oldTable->getTableSchema();

            $aQuestionsValidations=$oRequest->getPost('validate',array());
            foreach($aQuestionsValidations as $iQid=>$sValue)
            {
                if($sValue)
                {
                    $oQuestion=Question::model()->find("sid=:sid AND qid=:qid",array(":sid"=>$this->iSurveyId,":qid"=>"{$iQid}"));
                    if($this->setQuestionHidden($oQuestion->qid))
                        $aResult['success'][]="{$oQuestion->title} was hide to respondant";
                    else
                        $aResult['warning'][]="{$oQuestion->title} unable to hide to respondant";
                    // Hide comment question

                    if($oQuestion)
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
            }
            $aQuestionsValidations=$oRequest->getPost('q',array());
            foreach($aQuestionsValidations as $iQid=>$aQuestionValidations)
            {
                foreach($aQuestionValidations as $sType=>$aQuestionActions)
                {
                    foreach($aQuestionActions as $sAction=>$sDo)
                    {
                        if($sDo)
                            $this->doQuestion($iQid,$sType,$sAction,$oldSchema);
                    }
                }
            }
        }
        $aData=array();
        $this->displayContent($aData,array("result"));
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
        return PluginDynamic::model($this->sTableName)->count("{$sQuotedField}=:field{$sField}", array(":field{$sField}"=>$sValue));
    }
    private function getOldAnswerText($sField)
    {
        $sQuotedField=Yii::app()->db->quoteColumnName($sField);
        //return Yii::app()->db->createCommand("SELECT {$sQuotedField} FROM {{{$this->sTableName}}} WHERE {$sQuotedField} IS NOT NULL AND  {$sQuotedField}!=''")->queryAll();
        //Problem on prefix
        $aResult=Yii::app()->db->createCommand("SELECT {$sQuotedField} FROM {$this->sTableName} WHERE {$sQuotedField} IS NOT NULL AND {$sQuotedField}!=''")->queryAll();
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
            $oAnswer=Answer::model()->find("qid=:qid and assessment_value!=0",array(":qid"=>$oQuestion->qid));
            if($oAnswer)
                $aoQuestionsInfo[]=$oQuestion;
        }
        return $aoQuestionsInfo;
    }
    private function doQuestion($iQid,$sType,$sAction,$oldSchema=NULL)
    {
        // Validate the delphi question
        $oQuestionBase=Question::model()->find("sid=:sid AND language=:language AND qid=:qid",array(":sid"=>$this->iSurveyId,":language"=>$this->sLanguage,":qid"=>"{$iQid}"));
        if(!$oQuestionBase)
        {
            $this->addResult("No question {$iQid} in survey",'error');
            return false;
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
                switch ($sType)
                {
                    case 'comm':
                        foreach($aLangs as $sLang)
                        {
                            $newQuestionText = $this->get("commenttext_{$sLang}", 'Survey', $this->iSurveyId,$this->get('commenttext',null,null,$this->settings['commenttext']['default']));
                            Question::model()->updateAll(array('question'=>$newQuestionText),"sid=:sid AND title=:title AND language=:language",array(":sid"=>$this->iSurveyId,":title"=>$oQuestionBase->title.$sType,":language"=>$sLang));
                        }
                        break;
                    case 'cgd':
                        foreach($aLangs as $sLang)
                        {
                            $newQuestionText = $this->get("commentalttext_{$sLang}", 'Survey', $this->iSurveyId,$this->get('commentalttext',null,null,$this->settings['commentalttext']['default']));
                            Question::model()->updateAll(array('question'=>$newQuestionText),"sid=:sid AND title=:title AND language=:language",array(":sid"=>$this->iSurveyId,":title"=>$oQuestionBase->title.$sType,":language"=>$sLang));
                        }
                        break;
                    default:
                        break;
                }
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
                            $newQuestionText = "<p class='kce-title'>".$this->get("historytext_{$sLang}", 'Survey', $this->iSurveyId,$this->get('historytext',null,null,$this->settings['historytext']['default']))."</p>";
                            $newQuestionText .= "<div class='kce-historytext'>".$oQuestionBase->question."</div>";
                            Question::model()->updateAll(array('question'=>$newQuestionText),"sid=:sid AND title=:title AND language=:language",array(":sid"=>$this->iSurveyId,":title"=>$oQuestionBase->title.$sType,":language"=>$sLang));
                            $this->addResult("{$oQuestionBase->title}{$sType} question text updated",'success');
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
                    if($oQuestionExist)
                    {
                        $sColumnName=$this->getOldField($oldSchema,$oQuestionExist->qid);
                        if($sColumnName)
                        {
                            $baseQuestionText =$this->getOldAnswerText($sColumnName->name);
                            foreach($aLangs as $sLang)
                            {
                                $newQuestionText = "<p class='kce-title comment-title'>".$this->get("commenthist_{$sLang}", 'Survey', $this->iSurveyId,$this->get('commenthist',null,null,$this->settings['commenthist']['default']))."</p>".$baseQuestionText;
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
                                $newQuestionText = "<p class='kce-title comment-title'>".$this->get("commentalthist_{$sLang}", 'Survey', $this->iSurveyId,$this->get('commentalthist',null,null,$this->settings['commentalthist']['default']))."</p>".$baseQuestionText;
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
    private function createQuestion($sCode,$sType,$iGid,$iOrder)
    {
        //Need to renumber all questions on or after this
        $iOrder++;
        $sQuery = "UPDATE {{questions}} SET question_order=question_order+1 WHERE sid=:sid AND gid=:gid AND question_order >= :order";
        Yii::app()->db->createCommand($sQuery)->bindValues(array(':sid'=>$this->iSurveyId,':gid'=>$iGid, ':order'=>$iOrder))->query();
        $oQuestion= new Question;
        $oQuestion->sid = $this->iSurveyId;
        $oQuestion->gid = $iGid;
        $oQuestion->title = $sCode.$sType;
        $oQuestion->question = '';
        $oQuestion->help = '';
        $oQuestion->preg = '';
        $oQuestion->other = 'N';
        $oQuestion->mandatory = 'N';
        
        $oQuestion->type=$this->aDelphiCodes[$sType]['questiontype'];
        $oQuestion->question_order = $iOrder;
        $oSurvey=Survey::model()->findByPk($this->iSurveyId);
        $oQuestion->language = $oSurvey->language;
        if($oQuestion->save())
        {
            $iQuestionId=$oQuestion->qid;
            $aLang=$oSurvey->additionalLanguages;
            foreach($aLang as $sLang)
            {
                $oLangQuestion= new Question;
                $oLangQuestion->sid = $this->iSurveyId;
                $oLangQuestion->gid = $iGid;
                $oLangQuestion->qid = $iQuestionId;
                $oLangQuestion->title = $sCode.$sType;
                $oLangQuestion->type = $oQuestion->type;
                $oLangQuestion->question_order = $iOrder;
                $oLangQuestion->language = $sLang;
                if(!$oLangQuestion->save())
                    tracevar($oLangQuestion->getErrors());
            }
            return $iQuestionId;
        }
        $this->addResult("Unable to create question {$sCode}{$sType}, please contact the software developer.",'error',$oQuestion->getErrors());
    }
    private function setQuestionHidden($iQid)
    {
            $oAttribute=QuestionAttribute::model()->find("qid=:qid AND attribute='hidden'",array(":qid"=>$iQid));
            if(!$oAttribute)
                {
                    $oAttribute=new QuestionAttribute;
                    $oAttribute->qid=$iQid;
                    $oAttribute->attribute="hidden";
                }
            $oAttribute->value=1;
            if($oAttribute->save())
                return true;
            else
                $this->addResult("Unable to set {$iQid} hidden",'error',$oAttribute->getErrors());
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
        $oQuestionResult=Question::model()->find("sid=:sid AND language=:language AND title=:title",array(":sid"=>$this->iSurveyId,":language"=>$this->sLanguage,":title"=>"{$sCode}{$sType}"));
        $aQuestionsSettings=array();
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
    /**
    * @param $iQid : base question qid
    * @param $sCode : base question title
    * @param $sType : new question type
    */
    private function getValidateQuestionSettings($iQid,$sCode,$sType,$sValue=NULL)
    {
        $aDelphiCodes=$this->aDelphiCodes;
        $oQuestionResult=Question::model()->find("sid=:sid AND language=:language AND title=:title",array(":sid"=>$this->iSurveyId,":language"=>$this->sLanguage,":title"=>"{$sCode}{$sType}"));
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
        elseif($sType)
        {
            tracevar(array($sType,$sString));
        }
        if($oTrace)
            tracevar($oTrace);
    }
}

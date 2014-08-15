<?php

namespace albertborsos\yii2cms\models;

use albertborsos\yii2cms\components\DataProvider;
use albertborsos\yii2lib\db\ActiveRecord;
use albertborsos\yii2lib\helpers\S;
use albertborsos\yii2tagger\models\Tags;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "tbl_cms_posts".
 *
 * @property string $id
 * @property string $language_id
 * @property string $post_type
 * @property integer $parent_post_id
 * @property string $name
 * @property string $content_preview
 * @property string $content_main
 * @property integer $order_num
 * @property string $commentable
 * @property string $date_show
 * @property integer $created_at
 * @property integer $created_user
 * @property integer $updated_at
 * @property integer $updated_user
 * @property string $status
 *
 * @property PostSeo $seo
 * @property Languages $language
 */
class Posts extends ActiveRecord
{
    const STATUS_ACTIVE   = 'a';
    const STATUS_INACTIVE = 'i';
    const STATUS_DELETED  = 'd';

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'tbl_cms_posts';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['language_id'], 'required'],
            [['language_id', 'parent_post_id', 'order_num', 'created_at', 'created_user', 'updated_at', 'updated_user'], 'integer'],
            [['content_preview', 'content_main'], 'string'],
            [['date_show'], 'safe'],
            [['post_type'], 'string', 'max' => 100],
            [['name'], 'string', 'max' => 160],
            [['commentable', 'status'], 'string', 'max' => 1]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'language_id' => 'Nyelv',
            'post_type' => 'Típus',
            'name' => 'Főcím',
            'parent_post_id' => 'Szülő menüpont',
            'content_preview' => 'Előnézet',
            'content_main' => 'Tartalom',
            'order_num' => 'Sorrend',
            'commentable' => 'Hozzá lehet szólni?',
            'date_show' => 'Megjelenés ideje',
            'created_at' => 'Létrehozva',
            'created_user' => 'Létrehozta',
            'updated_at' => 'Módosítva',
            'updated_user' => 'Módosította',
            'status' => 'Státusz',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSeo()
    {
        return $this->hasOne(PostSeo::className(), ['post_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getLanguage()
    {
        return $this->hasOne(Languages::className(), ['id' => 'language_id']);
    }

    public function beforeValidate()
    {
        if (parent::beforeValidate()){
            if ($this->parent_post_id == ''){
                $this->parent_post_id = null;
            }
            return true;
        }else{
            return false;
        }
    }

    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)){
            $this->setOwnerAndTime();
            return true;
        }else{
            return false;
        }
    }

    public function beforeDelete()
    {
        if (parent::beforeDelete()){
            return true;
        }else{
            return false;
        }
    }

    public function createSeo()
    {
        $seo                    = new PostSeo();
        $seo->post_id           = $this->id;
        $seo->canonical_post_id = $this->id;
        $seo->title             = $this->name;
        $seo->status            = DataProvider::STATUS_ACTIVE;

        return $seo;
    }

    public function checkUrlIsCorrect(){
        $urlActual  = Yii::$app->request->getAbsoluteUrl();
        $urlCorrect = self::generateUrl($this->id);

        if ($urlActual !== $urlCorrect){
            return Yii::$app->controller->redirect($urlCorrect, 301);
        }
    }

    public static function generateUrl($postId){
        $link = ['/'];
        if (!is_null($postId)){
            $post = Posts::findOne(['id' => $postId]);
            if (!is_null($post)){
                if (!is_null($post->seo->url)){
                    return Yii::$app->urlManager->createAbsoluteUrl($post->seo->url);
                }else{
                    switch($post->post_type){
                        case 'MENU':
                            if ($post->order_num === 1){
                                $link = ['/'];
                            }else{
                                $link = ['/'.DataProvider::replaceCharsToUrl($post->name).'-'.$post->id.'.html'];
                            }
                            break;
                        case 'BLOG':
                            $link = ['/blog/'.DataProvider::replaceCharsToUrl($post->name).'-'.$post->id.'.html'];
                            break;
                    }
                    return Yii::$app->urlManager->createAbsoluteUrl($link);
                }
            }else{
                return null;
            }
        }else{
            return null;
        }
    }

    public function setContent(){
        switch($this->post_type){
            case 'BLOG':
                return Yii::$app->controller->renderPartial('_blog', [
                    'post' => $this,
                    'tags' => Tags::getAssignedTags($this, true, 'link'),
                ]);
                break;
            case 'MENU':
                return Yii::$app->controller->renderPartial('_menu', [
                    'post' => $this,
                    'tags' => Tags::getAssignedTags($this, true, 'link'),
                ]);
                break;
        }
        return false;
    }

    public static function getOrdersSourceArray(){
        $sourceArray = [];
        for($i = 1; $i <= 20; $i++){
            $sourceArray[$i] = $i;
        }
        return $sourceArray;
    }

    public static function getSelectParentMenu($actual = null, $addNull = false){
        $sql  = 'SELECT * FROM '.self::tableName().' WHERE status=:status_a AND (post_type=:type_MENU OR post_type=:type_DROP)';
        if (!is_null($actual)){
            $sql .= ' AND id<>:id';
        }
        $sql .= ' ORDER BY order_num ASC';
        $cmd = Yii::$app->db->createCommand($sql);
        $cmd->bindValue(':status_a', DataProvider::STATUS_ACTIVE);
        $cmd->bindValue(':type_MENU', 'MENU');
        $cmd->bindValue(':type_DROP', 'DROP');
        if (!is_null($actual)){
            $cmd->bindParam(':id', $actual);
        }

        $menus = $cmd->queryAll();

        $return = ArrayHelper::map($menus, 'id', 'name');
        if ($addNull){
            $return[''] = 'Nincs';
        }
        return $return;
    }

    public static function getPostName($id){
        if (!is_null($id)){
            $post = Posts::findOne(['id' => $id]);
            if (!is_null($post)){
                return $post->name;
            }
        }
        return 'Nincs';
    }

}

<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "sitemap".
 *
 * @property integer $id
 * @property string $filename
 * @property string $priority
 * @property string $changefreq
 * @property integer $is_static
 * @property integer $is_compressed
 * @property integer $is_child
 * @property integer $is_active
 */
class Sitemap extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'sitemap';
    }

}

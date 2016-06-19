<?php

use yii\db\Migration;

class m160618_144401_Init extends Migration
{
    public function up()
    {
        $this->createTable('sitemap', [
            'id' => $this->primaryKey(),
            'filename' => $this->string(),
            'priority' => $this->string(),
            'changefreq' => $this->string(),
            'is_static' => $this->integer(1)->defaultValue(1),
            'is_compressed' => $this->integer(1)->defaultValue(1),
            'is_child' => $this->integer(1)->defaultValue(1),
            'is_active' => $this->integer(1)->defaultValue(1),
        ]);

        $this->insert('sitemap', array(
            'filename' => 'sitemap.xml',
            'priority' => 1,
            'changefreq' => 'MONTHLY',
            'is_static' => 1,
            'is_compressed' => 0,
            'is_child' => 0,
            'is_active' => 1,
        ));

        $this->insert('sitemap', array(
            'filename' => 'site-index.xml',
            'priority' => 1,
            'changefreq' => 'MONTHLY',
            'is_static' => 1,
            'is_compressed' => 1,
            'is_child' => 1,
            'is_active' => 1,
        ));

        $this->insert('sitemap', array(
            'filename' => 'content-index.xml',
            'priority' => 1,
            'changefreq' => 'MONTHLY',
            'is_static' => 0,
            'is_compressed' => 1,
            'is_child' => 1,
            'is_active' => 1,
        ));


        return true;
    }

    public function down()
    {
        $this->dropTable('sitemap');

        return true;
    }

    /*
    // Use safeUp/safeDown to run migration code within a transaction
    public function safeUp()
    {
    }

    public function safeDown()
    {
    }
    */
}

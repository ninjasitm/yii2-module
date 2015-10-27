<?php

use yii\db\Schema;
use yii\db\Migration;

class m150314_143557_create_category_list_table extends Migration
{
    public function up()
    {
		$tableSchema = \Yii::$app->db->getTableSchema('category_list');
		if($tableSchema)
			return true;
		$this->createTable('category_list', [
            'id' => 'pk',
            'author_id' => Schema::TYPE_INTEGER . ' NOT NULL',
            'created_at' => Schema::TYPE_TIMESTAMP . ' NOT NULL DEFAULT NOW()',
            'editor_id' => Schema::TYPE_INTEGER . ' NULL',
            'updated_at' => Schema::TYPE_TIMESTAMP . ' NULL',
            'remote_id' => Schema::TYPE_INTEGER . ' NOT NULL',
            'remote_type' => Schema::TYPE_STRING . '(32) NOT NULL',
            'remote_class' => Schema::TYPE_TEXT . ' NOT NULL',
            'category_id' => Schema::TYPE_INTEGER . ' NOT NULL',
            'priority' => Schema::TYPE_INTEGER . ' NULL',
            'deleted' => Schema::TYPE_BOOLEAN . ' NULL DEFAULT false',
            'deleted_at' => Schema::TYPE_TIMESTAMP . ' NULL',
            'deleted_by' => Schema::TYPE_INTEGER . ' NULL',
            'disabled' => Schema::TYPE_BOOLEAN . ' NULL DEFAULT false',
            'disabled_at' => Schema::TYPE_TIMESTAMP . ' NULL',
            'disabled_by' => Schema::TYPE_INTEGER . ' NULL',
        ]);
		
		$this->createIndex('category_list_category_id', '{{%category_list}}', ['category_id']);
		$this->createIndex('category_list_remote_id', '{{%category_list}}', ['remote_id']);
		$this->createIndex('category_list_unique', '{{%category_list}}', [
			'remote_id', 'remote_type', 'category_id'
		], true);
       
	    $this->addForeignKey('fk_category_list_author', '{{%category_list}}', 'author_id', '{{%user}}', 'id', 'CASCADE', 'RESTRICT');
	    $this->addForeignKey('fk_category_list_editor', '{{%category_list}}', 'editor_id', '{{%user}}', 'id', 'CASCADE', 'RESTRICT');
	    $this->addForeignKey('fk_category_list_category', '{{%category_list}}', 'category_id', '{{%categories}}', 'id', 'CASCADE', 'RESTRICT');
		
		/**
		 * Create the metadata table
		 */
		$this->createTable('category_list_metadata', [
            'id' => 'pk',
            'content_id' => Schema::TYPE_INTEGER . ' NOT NULL',
            'key' => Schema::TYPE_STRING . '(32) NOT NULL',
            'value' => Schema::TYPE_TEXT . ' NOT NULL',
            'created_at' => Schema::TYPE_TIMESTAMP . ' NOT NULL DEFAULT NOW()',
            'updated_at' => Schema::TYPE_TIMESTAMP . ' NULL',
        ]);
		
	    $this->addForeignKey('fk_category_list_metadata', '{{%category_list_metadata}}', 'content_id', '{{%category_list}}', 'id', 'CASCADE', 'RESTRICT');
    }

    public function down()
    {
        echo "m150314_143557_create_category_list_table cannot be reverted.\n";

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

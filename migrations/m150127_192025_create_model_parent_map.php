<?php

use yii\db\Schema;
use yii\db\Migration;

class m150127_192025_create_model_parent_map extends Migration
{
    public function up()
    {
		$tableSchema = \Yii::$app->db->getTableSchema('model_parent_map');
		if($tableSchema)
			return true;
			
		$this->createTable('model_parent_map', [
            'id' => 'pk',
            'remote_type' => Schema::TYPE_STRING . '(32) NOT NULL',
            'remote_id' => Schema::TYPE_INTEGER . ' NOT NULL',
            'remote_class' => Schema::TYPE_TEXT . ' NOT NULL',
            'remote_table' => Schema::TYPE_STRING . '(32) NOT NULL',
            'parent_type' => Schema::TYPE_STRING . '(32) NOT NULL',
            'parent_id' => Schema::TYPE_INTEGER . ' NOT NULL',
            'parent_class' => Schema::TYPE_TEXT . ' NOT NULL',
            'parent_table' => Schema::TYPE_STRING . '(32) NOT NULL',
        ]);
		
		$this->createIndex('unique_parent', 'model_parent_map', [
			'remote_type', 'remote_id', 'remote_table', 
			'parent_type', 'parent_id', 'parent_table'
		], true);
    }

    public function down()
    {
        echo "m150127_192025_create_model_parent_map cannot be reverted.\n";

        return false;
    }
}

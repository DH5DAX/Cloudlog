<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_add_exchangetype_to_contest extends CI_Migration {

    public function up()
    {
        $fields = array(
            'exchangetype' => array(
                'type' => 'VARCHAR',
                'constraint' => '20',
                'null' => TRUE,
                'default' => 'serial'
            )
        );
        $this->dbforge->add_column('contest', $fields);
    }

    public function down()
    {
        $this->dbforge->drop_column('contest', 'exchangetype');
    }
}

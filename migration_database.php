<?php
/**
 * Created by PhpStorm.
 * User: anony
 * Date: 02/08/2017
 * Time: 17:44
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * array de configuração do migrate
 * arr = [
 * "src" => [
 *      user => '',
 *      host => '',
 *      pass => '',
 *      database => ''
 *  ],
 *  "clone" => [
 *      user => '',
 *      host => '',
 *      pass => '',
 *      database => ''
 *  ]
 * ]
 **/

class Migration_Database
{
    private $array=[];
    private $conn;
    private $table;
    private $pk;


    /**
     * conectando com os banco de dados
     **/
    private function conn()
    {
        foreach($this->array as $db_origin => $a){
            try{

                $this->conn[$db_origin] = new PDO("mysql:host=".$this->array[$db_origin]['host'].";dbname=".$this->array[$db_origin]['database'], $this->array[$db_origin]['user'], $this->array[$db_origin]['pass'], array(\PDO::ATTR_PERSISTENT => true));

            }catch(Exception $e){
                print $e->getMessage();
            }
        }
    }

    public function __construct($array)
    {
        if(is_array($array)) $this->array = $array;
        else{
            print 'informe o array de configuração';
            die();
        }

    }
    /**
     * metodo responsavel pro fazer a migração de dados
     * aqui é tipo o setup do migrate
     *  array
     * [
     *      pk =>  '',
     *      table => ''
     * ]
     **/
    public function migrate($array)
    {

        if(!array_key_exists('pk',$array)){
            print '<br>Não foi encontrado no array "pk"';
            die();
        }else if(!array_key_exists('table',$array)){
            print '<br>Não foi encontrado no array "table"';
            die();
        }else if(isset($array['where'])){
            if(!is_array($array['where'])){
                print '<br>Não foi encontrado no array o "where" que é um array onde chave e valor ex. "id"=>"3"';
                die();
            }else
                {
                    /**
                     * caso tenh a um ou mais wheres(especificidade de dados a serem atualizados)
                     * ele vai deixar montado o where para as querys.
                    **/
                    $q_where = [];
                    foreach($array['where'] as $k=>$v){
                        $q_where[] = "$k='$v'";
                    }

                    $array['where'] = implode(" and ", $q_where);
                }

        }

        //ajeitando as variaveis
        $this->table = $array['table'];
        $this->pk= $array['pk'];
        $this->where = isset($array['where']) ? "where ".$array['where'] : "";

        //conectando
        $this->conn();

        //pegando os dados de src e preparando eles
        $search_table_src = $this->search_table('src');

        $columns_src = $search_table_src['colunms'];
        $data_src = $search_table_src['data'];

        //pegando informações do clone
        $search_table_clone = $this->search_table('clone');

        $columns_clone = $search_table_src['colunms'];

        $this->copy_data($search_table_src);


        print "<br><b>Finalizado com sucesso</b>";

    }


    /**
     * medodo responsavel por procurar a tabela e retornar as informações de nome de colulas e
     * dados.
    **/
    private function search_table($origin)
    {
        try{
            $name_colunm = [];

            //vendo query
            /**
             * TODO fazer com que ele receba uma condição de search .. ta errado aqui owhere é só para teste
             */

            $smtp = $this->conn[$origin]->query("select * from $this->table $this->where");

            /**
             * condicional caso seja src e não encontre a tabela ele para toda a execução
             **/
            if($smtp->errorCode() != '00000' and $origin == 'src'){
                print 'Tabela de origem não encontrada';
                die();
            }


            /**
             * condicional caso seja o clone que esteja acessando
             * e ele vai e vê se eiste a table caso não ele clona a tabela.
            **/
            if($smtp->errorCode() != '00000' and $origin == 'clone'){
                 //cria a tabela
                $this->create_table_clone();
            }


            /**
             * pegando os nomes da coluna da tabela
            **/

            $colnm = $this->conn[$origin]->query("SHOW COLUMNS FROM $this->table")->fetchAll(2);

            foreach($colnm as $c){
                $name_colunm[] = $c['Field'];
            }


            /**
             * pegando todas as informações da tabala(massa de dados)
            **/
            $data = $this->conn[$origin]->query("select * from $this->table $this->where")->fetchAll(PDO::FETCH_ASSOC);

            return ["colunms" => $name_colunm, "data" => $data];

        }catch(Exception $e){
            print $e->getMessage();
        }
    }


    /**
     * criando clone da table mae
    **/
    private function create_table_clone()
    {
        $fields = [];
        $its = $this->conn['src']->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'multiplicador' AND TABLE_NAME = 'user'")->fetchAll(2);

        foreach($its as $it){
            $fields[] = $it['COLUMN_NAME'] ." ". $it['COLUMN_TYPE'] ." ".($it['IS_NULLABLE']=="NO"?"NOT NULL":"")." ".($it['COLUMN_KEY']=="PRI"?"PRIMARY KEY":"")." ".($it['EXTRA']=="auto_increment"?"AUTO_INCREMENT":"");
        }

        $fields = implode(',', $fields);

        $smtp = $this->conn['clone']->query("create table $this->table ($fields)");

        if($smtp->errorCode() != '00000'){
            print 'error ao criar a tabela '.$this->table;
            die();
        }

        print "<br><b>Tabela Criada no clone</b> name: $this->table";

    }


    /**
     * metodo resonsavel por forçar a criação de todos os registros
     * ele deleta qualquer coisa na tabela de clone e cria tudo de novo
     * baseado na src
    **/
    private function force_create($src)
    {
        $this->conn['clone']->query("delete from $this->table where $this->where");

        foreach($src['data'] as $s)
        {
            $this->create_register($s);
        }

    }

    private function copy_data($src)
    {

        foreach($src['data'] as $s)
        {
            /**
             * TODO -> verificação zuada.. da uma olhada depois
            **/
            $smtp = $this->conn['clone']->query("select " .$this->pk. " from ".$this->table." where ".$this->pk." = '".$s[$this->pk]."'")->fetch(2);

            if($smtp == false)
                $this->create_register($s);
            else
                $this->update_register($s);
        }

        print "<br><b>Dados Copiados:</b> Qtd. registros".count($src['data']);
    }


    /**
     * criando registro
    **/

    public function create_register($arr)
    {
        $keys = [];
        $keys_dot = [];
        $values = [];

        foreach($arr as $k=>$p)
        {
            $keys[] = $k;
            $keys_dot[] = ":".$k;
            $values[":".$k] = $p;
        }

        $keys = implode(',', $keys);
        $keys_dot = implode(',', $keys_dot);


        $query = "INSERT INTO $this->table ($keys) VALUES ($keys_dot)";


        $stmt = $this->conn['clone']->prepare($query);
        $stmt->execute($values);

        print "<br><b>Criado</b> o registro na table $this->table, [pk=$this->pk]".$arr[$this->pk];

    }

    /**
     * atualizando registros
    **/

    public function update_register($arr)
    {
        $values = [];

        foreach($arr as $k=>$p)
        {
            if(gettype($p) == 'string')
                $values[] = "$k='$p'";
            else
                $values[] = "$k=$p";
        }

        $velues = implode(',', $values);


        $query = "UPDATE $this->table SET $velues WHERE $this->pk = ".$arr[$this->pk];


        $stmt = $this->conn['clone']->query($query);

        print "<br><b>atualizado</b> o registro na table $this->table, [pk=$this->pk]".$arr[$this->pk];

    }

    private function validate_array_conn($array_old)
    {
        $array = [
            "src" => ["user","host","pass","database"],
            "clone" => ["user","host","pass","database"]
        ];

        foreach($array as $key => $a){
            foreach($a as $key_b => $b){

            }
        }
    }

}


$ar = [
    "src" => [
        "user" => '',
        "host" => '.db.4855800.hostedresource.com',
        "pass" => '@',
        "database" => ''
    ],
    "clone" => [
        "user" => '',
        "host" => '',
        "pass" => '@',
        "database" => 'pi'
    ],
];

$migdb = new Migration_Database($ar);

$migdb->migrate(['table'=>'qualidade', "pk"=>'', 'where'=>['key'=>'val', 'key2'=>'val2']]);

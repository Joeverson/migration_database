# migration_database
migrate yours data between databases, easy and fast you can migrate datas of the a database sorce fro database clone or just table of the source database.
# How use?

Define the server source and the serve that clone in you database

```$ar = [
    "src" => [
        "user" => "",
        "host" => ".db.4855800.hostedresource.com",
        "pass" => "@",
        "database" => ""
    ],
    "clone" => [
        "user" => "dev",
        "host" => "localhost",
        "pass" => "@",
        "database" => ""
    ],
];
$migdb = new Migration_Database($ar);
$migdb->migrate(["table"=>"qualidade", "pk"=>['pk1', 'pk2'], "where"=>["key"=>"value"]]);```

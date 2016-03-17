<!doctype html>
<html>
    <head>
        <title>Search payments from two e-mails in January</title>
    </head>
    <body>
        <?php
        /**
         * MercadoPago SDK
         * Search payments from two e-mails in January
         * @date 2012/03/29
         * @author hcasatti
         */
        // Include Mercadopago library
        require_once "../../lib/mercadopago.php";

        // Create an instance with your MercadoPago credentials (CLIENT_ID and CLIENT_SECRET): 
        // Argentina: https://www.mercadopago.com/mla/herramientas/aplicaciones 
        // Brasil: https://www.mercadopago.com/mlb/ferramentas/aplicacoes
        // Mexico: https://www.mercadopago.com/mlm/herramientas/aplicaciones 
        // Venezuela: https://www.mercadopago.com/mlv/herramientas/aplicaciones 
        $mp = new MP("CLIENT_ID", "CLIENT_SECRET");

        // Sets the filters you want
        $filters = array(
            "payer_email" => "mail02@mail02.com%20mail01@mail01.com",
            "begin_date" => "2011-01-01T00:00:00Z",
            "end_date" => "2011-02-01T00:00:00Z"
        );

        // Search payment data according to filters
        $searchResult = $mp->search_payment($filters);

        // Show payment information
        ?>
        <table border='1'>
            <tr><th>id</th><th>site_id</th><th>external_reference</th><th>status</th></tr>
            <?php
            foreach ($searchResult["response"]["results"] as $payment) {
                ?>
                <tr>
                    <td><?php echo $payment["collection"]["id"]; ?></td>
                    <td><?php echo $payment["collection"]["site_id"]; ?></td>
                    <td><?php echo $payment["collection"]["external_reference"]; ?></td>
                    <td><?php echo $payment["collection"]["status"]; ?></td>
                </tr>
                <?php
            }
            ?>
        </table>
    </body>
</html>

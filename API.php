<?php
class ApiLolz
{
    private $apiKey;
    private $apilinkMarker = 'api.lzt.market/';
    private $apiLinkForum = 'api.zelenka.guru/';
    protected $APIKEY = ""; //ключ бота тг
    protected $IPID = ""; // id чат общего телеграмм
    protected $MYIP = ""; // ваш id чата телеграмм

    protected $count = 0;
    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
        $this->getCountNeedsBots();
    }
    public function getProfileData()
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.zelenka.guru/market/me?oauth_token='.$this->apiKey,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        return json_decode($response);
    }
    protected function telegramMess($textMess,$id)
    {
        $ch = curl_init();
        curl_setopt_array(
            $ch,
            array(
                CURLOPT_URL => 'https://api.telegram.org/bot' . $this->APIKEY . '/sendMessage',
                CURLOPT_POST => TRUE,
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_POSTFIELDS => array(
                    'chat_id' => $id,
                    'text' => $textMess,
                ),
            )
        );
        $responce = curl_exec($ch);
        $errors = curl_error($ch);

        if (!$errors) {
            return json_decode($responce);
        }
        curl_close($ch);
    }
    public function getProfile()
    {
        return $this->getProfileData();
    }
    public function categorySearch($nameCat,$minPrice,$maxPrice)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.lzt.market/'.$nameCat.'?pmax='.$maxPrice.'&pmin='.$minPrice.'',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer '.$this->apiKey.'',
                'Content-Type: application/json; charset=UTF-8'
            ),
        ));
        $response = curl_exec($curl);

        curl_close($curl);
        return json_decode($response);
    }
    public function getCategory($nameCat,$minPrice,$maxPrice)
    {
        return $this->categorySearch($nameCat,$minPrice,$maxPrice);
    }
    public function getCountNeedsBots()
    {
        $numOfBots = R::count( ''); // база для подсчета необходимого колличество покупки страниц
        $result = 5 - $numOfBots;
        $this->count = $result;
        return $result;
    }
    public function collectionIdForByVkAutoreg()
    {
        $arrayIdProduct = $this->categorySearch('vkontakte', 5, 12);
        $newArray = [];
            foreach ($arrayIdProduct->items as $key => $item) {
                $newArray[$key]['id'] = $item->item_id;
                $newArray[$key]['price'] = $item->price;
            }

        $newArray = array_slice($newArray, 0, $this->count);
        return $newArray;
    }
    public function countSumBuy()
    {
        $array = $this->collectionIdForByVkAutoreg();
        $balance = 0;
        foreach ($array as $item) {
            $balance += $item['price'];
        }
        return $balance;
    }
    public function checkBalance()
    {
        $balance = $this->getBalance();
        $sumBuy = $this->countSumBuy();

        if ($balance < $sumBuy) {
            $text = '#покупка '.date('d.m').':
На балансе недостаточно средств для совершения покупок
Доступный баланс: '.$balance.'₽';
            $this->telegramMess($text, $this->MYIP);
            return false;
        }
        return true;
    }
    public function getBalance()
    {
        return $this->getProfile()->user->balance;
    }
    protected function byuAccount($id,$price) {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.lzt.market/'.$id.'/fast-buy?price='.$price.'',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer '.$this->apiKey.''
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return json_decode($response);
    }
    public function mainAccountBy()
    {
        $countNeedsBots = $this->getCountNeedsBots();
        if ($countNeedsBots > 0 && $countNeedsBots <= 5){
            if ($this->checkBalance() !== true) {
                return;
            }
            sleep(7);
            $dataFromBuy = $this->collectionIdForByVkAutoreg();
            sleep(5);

            $botBuyCount = 0;
            $priceFromBuy = 0;
            foreach ($dataFromBuy as $itemData) {
                $dataAccount = $this->byuAccount($itemData['id'],$itemData['price']);
                if ($dataAccount->status !== 'error') {
                    $this->sendLogin(trim($dataAccount->item->loginData->login),$dataAccount->item->loginData->password,$itemData['price']);
                    $botBuyCount +=1;
                    $priceFromBuy += $itemData['price'];
                } else {
                    $this->telegramMess($dataAccount,$this->MYIP);
                    return;
                }
                sleep(10);
            }
            if ($botBuyCount == 1) {
                $messagesTg = $botBuyCount.' бот';
            } elseif ($botBuyCount > 1 OR $botBuyCount <= 4) {
                $messagesTg = $botBuyCount.' бота';
            } else {
                $messagesTg = $botBuyCount.' ботов';
            }
            $text = '#покупка '.date('d.m').':
покупка '.$messagesTg.'
на сумму '.$priceFromBuy.' ₽
Доступный баланс: '.$this->getBalance().' ₽';
            if ($botBuyCount !== 0) {
                $this->telegramMess($text,$this->MYIP);
            } else {
                $text = '#покупка '.date('d.m').':
Произошла ошибка
Доступный баланс: '.$this->getBalance().' ₽';
                $this->telegramMess($text,$this->MYIP);
            }
        }

    }
    public function sendLogin($login,$pass,$price)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => '', //ссылка на скрип для дальнейше работы с данными
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => ['login' => $login,'pass' => $pass, 'price' => $price],
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic '
            ],
        ]);

        $response = curl_exec($curl);

        curl_close($curl);
        return json_decode($response);
    }
    public function notifLowMoney()
    {
        $balance = $this->getBalance();
        if ($balance < 50) {
            $text = '#Оповещение '.date('d.m').':
пополните баланс.
Доступный баланс: '.$balance.'₽';
            $this->telegramMess($text, $this->MYIP);
        }
    }
}

$ApiLolz = new  ApiLolz(''); //API KEY
sleep(8);
$ApiLolz->mainAccountBy();
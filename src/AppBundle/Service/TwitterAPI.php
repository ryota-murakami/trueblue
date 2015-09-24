<?php

namespace AppBundle\Service;

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use HWI\Bundle\OAuthBundle\Security\Core\Authentication\Token\OAuthToken;
use Doctrine\Bundle\DoctrineBundle\Registry;

class TwitterAPI
{
    /**
    * @var Doctrine\Bundle\DoctrineBundle\Registry
    */
    protected $doctrine;

    /**
    * @var Symfony\Component\Security\Core\Authentication\Token\Storage
    */
    protected $tokenStorage;

    protected $consumer_key = ''; // api key
    protected $consumer_secret = ''; // api secret
    protected $bearer_token = '';

    protected $request_url = ''; // decide by api call method

    /**
     * @param TokenStorage $tokenStorage $this->container->get('security.token_storage')
     * @param array $key_and_token twitter api key and tokens
     */
    public function __construct(Registry $doctrine, TokenStorage $tokenStorage, array $key_and_token)
    {
        if ($tokenStorage->getToken() instanceof OAuthToken === false) {
            throw new InvalidArgumentException(sprintf('Object get from tokenstrage was not a OAuthToken. getting "%s" object.', get_class($tokenStorage->getToken())));
        }

        $this->doctrine = $doctrine;
        $this->tokenStorage = $tokenStorage;
        $this->consumer_key = $key_and_token['consumer_key'];
        $this->consumer_secret = $key_and_token['consumer_secret'];
        $this->bearer_token = $key_and_token['bearer_token'];
    }

    /**
     * get today timeline json
     *
     * @return array|null timeline or null
     */
    public function getTodayTimeline()
    {
        $get_query = ['user_id' => $this->tokenStorage->getToken()->getRawToken()['user_id']];
        $today = (new \DateTime())->format('Y-m-d');
        $since_id_at = $this->tokenStorage->getToken()->getUser()->getSinceIdAt();

        // 今日の始点ツイートのsince_idが無ければタイムラインをまるまる取得してsince_idを計算する
        if ($since_id_at === null || $since_id_at->format('Y-m-d') !== $today) {
            $timeline = $this->searchTodayTimeline($get_query);

            return $timeline;
        }
        // since_idがあればget_queryに指定して今日のつぶやき一覧をapiから取得
        if ('undefined' !== $today_since_id = $this->tokenStorage->getToken()->getUser()->getTodaySinceId()) {
            $get_query['since_id'] = $today_since_id;
        // since_idがundefinedなら200件まで取得 今日以前のつぶやきが存在しないアカウントなど
        } else {
            $get_query['count'] = '200';
        }
        $timeline = $this->callStatusesUserTimeline($get_query);

        return $timeline;
    }

    /**
     * search since_id & set db. return today timeline json
     *
     * @param array $get_query api parameters
     * @return array|null timeline or null
     */
    protected function searchTodayTimeline(array $get_query)
    {
        $today = (new \DateTime())->format('Y-m-d');
        $saved_timelime = array(); // 今までに取得したtimelime
        $index = 0; // for文を回した回数

        while (true) {
            // timeline取得apiを叩く 2回目以降はmax_idで指定したつぶやきも含まれるので切り捨てる
            $fetch_timelime = $index === 0 ? $this->callStatusesUserTimeline($get_query) : array_slice($this->callStatusesUserTimeline($get_query), 1);
            $saved_timelime = array_merge($saved_timelime, $fetch_timelime);

            // apiからの取得件数が0件
            if (count($fetch_timelime) < 1) {
                // timelineの総取得総数が0件 一件もつぶやきが無い人など
                if (count($saved_timelime) < 1) {
                    return null;
                // 直前に取得した分までが本日のつぶやき
                } else {
                    return $saved_timelime;
                }
            }

            // 今日一番最初のつぶやきのsince_idとtimelimeを抽出する
            for ($i=$index; count($saved_timelime) > $i; $i++) {
                $tweet = array_key_exists($i, $saved_timelime) ? $saved_timelime[$i] : null; // null 本日以前のつぶやきが見つからなかった

                // 本日以前のつぶやきであるか？ あるいは本日以前のつぶやきが存在しない
                if ($tweet === null || $today !== date('Y-m-d', strtotime($tweet->created_at))) {
                    // 本日のつぶやきが0件
                    if ($index === 0) {
                      return null;
                    }

                    // 本日のタイムラインを抽出
                    $today_timelime = array_slice($saved_timelime, 0, $index);

                    // sinceId情報をDBに保存
                    $userEntity = $this->doctrine->getRepository('AppBundle:User')->find($this->tokenStorage->getToken()->getUser()->getId());
                    $today_since_id = $tweet !== null ? $tweet->id_str : 'undefined'; // 本日以前のつぶやきが存在しない場合 undefined
                    $userEntity->setTodaySinceId($today_since_id);
                    $userEntity->setSinceIdAt(new \DateTime());
                    $em = $this->doctrine->getEntityManager();
                    $em->persist($userEntity);
                    $em->flush();

                    return $today_timelime;
                }
                $index++;
            }
            // api次回取得位置を指定
            $get_query = array_merge($get_query, array('max_id' => $tweet->id_str));
        }
    }

    /**
     * get timelime since_id from max_id
     *
     * @param string $since_id
     * @param string $max_id
     * @return array|null timeline or null
     */
    public function getTimelineSinceFromMax($since_id, $max_id)
    {
      if (!is_string($since_id) || !is_string($max_id)) {
          throw new InvalidArgumentException('TwitterAPI::getTimelineSinceFromMax() arguments must be string.');
      }

      $get_query = [
        'user_id' => $this->tokenStorage->getToken()->getRawToken()['user_id'],
        'since_id' => $since_id,
        'max_id' =>  $max_id,
      ];

      $decoded_json = $this->callStatusesUserTimeline($get_query);

      return $decoded_json;
    }

    /**
    * call api https://api.twitter.com/1.1/statuses/user_timeline.json
    *
    * @param array $get_query
    * @return stdClass $decoded_json
    */
    protected function callStatusesUserTimeline(array $get_query = array())
    {
        $this->request_url = 'https://api.twitter.com/1.1/statuses/user_timeline.json';

        if ($get_query) {
            $this->request_url = $this->concatGetQuery($this->request_url, $get_query);
        }

        $context = $this->createBearerAuthContext();

        $response_json = @file_get_contents($this->request_url, false, stream_context_create($context));
        $decoded_json = json_decode($response_json);

        return $decoded_json;
    }

    /**
    * call api https://api.twitter.com/1.1/search/tweets.json
    *
    * @param array $get_query
    * @return stdClass $decoded_json
    */
    protected function callSearchTweets(array $get_query = array())
    {
        $this->request_url = 'https://api.twitter.com/1.1/search/tweets.json';

        $this->request_url = $this->concatGetQuery($this->request_url, $get_query);

        $context = $this->createBearerAuthContext();

        $response_json = @file_get_contents($this->request_url, false, stream_context_create($context));
        $decoded_json = json_decode($response_json);

        return $decoded_json;
    }

    /**
     * create new BearerToken connect with twitter api
     * @return string BearerToken
     */
    public function createNewBearerToken()
    {
        $api_key = $this->consumer_key;
        $api_secret = $this->consumer_secret;

        // クレデンシャルを作成
        $credential = base64_encode($api_key . ':' . $api_secret);

        // リクエストURL
        $this->request_url = 'https://api.twitter.com/oauth2/token';

        // リクエスト用のコンテキストを作成する
        $context = array(
          'http' => array(
            'method' => 'POST',
            'header' => array(
              'Authorization: Basic ' . $credential,
              'Content-Type: application/x-www-form-urlencoded;charset=UTF-8' ,
            ),
            'content' => http_build_query(array( 'grant_type' => 'client_credentials')),
          ),
        );

        $response_json = @file_get_contents($this->request_url, false, stream_context_create($context));
        $decoded_json = json_decode($response_json);

        if ($decoded_json->token_type !== 'bearer') {
            throw new \Exeption('faild to get the BearerToken');
        }

        return $decoded_json->access_token;
    }

    /**
    * concat encoded get_query to http_request_url
    * @param string $request_url
    * @param string $get_query
    * @return string $request_url_with_query
    */
    protected function concatGetQuery($request_url, $get_query)
    {
        $request_url_with_query = $request_url . '?' . http_build_query($get_query);

        return $request_url_with_query;
    }

    /**
    * create bearer_token authrization http_request_context
    * @return array context
    */
    protected function createBearerAuthContext()
    {
        return array(
                 'http' => array(
                   'method' => 'GET',
                   'header' => array(
                     'Authorization: Bearer ' . $this->bearer_token,
                   ),
                 ),
               );
    }

    /**
     * Get the value of Token Storage
     *
     * @return mixed
     */
    public function getTokenStorage()
    {
        return $this->tokenStorage;
    }

    /**
     * Get the value of Consumer Key
     *
     * @return mixed
     */
    public function getConsumerKey()
    {
        return $this->consumer_key;
    }

    /**
     * Get the value of Consumer Secret
     *
     * @return mixed
     */
    public function getConsumerSecret()
    {
        return $this->consumer_secret;
    }

    /**
     * Get the value of Bearer Token
     *
     * @return mixed
     */
    public function getBearerToken()
    {
        return $this->bearer_token;
    }
}

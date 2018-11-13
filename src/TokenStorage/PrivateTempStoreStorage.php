<?php

declare(strict_types=1);

namespace Drupal\hydro_raindrop\TokenStorage;

use Adrenth\Raindrop\ApiAccessToken;
use Adrenth\Raindrop\Exception\UnableToAcquireAccessToken;
use Adrenth\Raindrop\TokenStorage\TokenStorage;
use Drupal\User\PrivateTempStore;

/**
 * Class PrivateTempStoreStorage
 *
 * @package Drupal\hydro_raindrop\TokenStorage
 */
class PrivateTempStoreStorage implements TokenStorage {
    /**
     * @var Drupal\User\PrivateTempStore
     */
    protected $tempStore;

    /**
     * @param PrivateTempStore $tempStore
     */
    public function __construct(PrivateTempStore $tempStore) {
      $this->tempStore = $tempStore;
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessToken(): ApiAccessToken {
      if (!$data = $this->tempStore->get('hydro_raindrop_token_data')) {
          throw new UnableToAcquireAccessToken('Access Token is not found in the storage.');
      }

      if (!empty($data) && substr_count($data, '|') === 1) {
          $data = explode('|', $data);
          return ApiAccessToken::create($data[0] ?? '', (int) ($data[1] ?? 0));
      }

      throw new UnableToAcquireAccessToken('Access Token is not found in the storage.');
    }

    /**
     * {@inheritdoc}
     */
    public function setAccessToken(ApiAccessToken $token) {
      $this->tempStore->set('hydro_raindrop_token_data', $token->getToken() . '|'. $token->getExpiresAt());
    }

    /**
     * {@inheritdoc}
     */
    public function unsetAccessToken() {
      $this->tempStore->delete('hydro_raindrop_token_data');
    }
}

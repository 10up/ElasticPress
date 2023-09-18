<?php
/**
 * NotFoundException class
 *
 * @since 4.7.0
 * @package elasticpress
 */

namespace ElasticPress\Exception;

use \ElasticPress\Vendor_Prefixed\Psr\Container\NotFoundExceptionInterface;

/**
 * NotFoundException class
 */
class NotFoundException extends \InvalidArgumentException implements NotFoundExceptionInterface {}

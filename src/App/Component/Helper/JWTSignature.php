<?php

namespace Ecard\Cms\App\Component\Helper;

use Ecard\Cms\App;
use Ecard\Cms\App\Component;
use Ecard\Cms\App\Exception\JWTSignatureException;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Builder;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Configuration;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Encoding\CannotDecodeContent;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Signer\Hmac\Sha256;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Signer\Key\InMemory;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Token\InvalidTokenStructure;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Token\UnsupportedHeaderFound;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Validation\Constraint\SignedWith;
use stdClass;

class JWTSignature extends Component
{
    /**
     * @param App $app
     *
     * @return void
     */
    public function __construct(
        $app = null
    ) {
        parent::__construct($app);
    }

    /**
     * @param array<string, mixed> $payload
     * @param string|null          $secretKey
     *
     * @return string
     */
    public function encode(
        $payload = [],
        $secretKey = null
    ) {
        if (true === empty($payload)) {
            throw new JWTSignatureException(JWTSignatureException::MSG_MISSING_PARAMETER_PAYLOAD);
        }

        if (true === empty($secretKey)) {
            throw new JWTSignatureException(JWTSignatureException::MSG_MISSING_PARAMETER_SIGNINGKEY);
        }

        $builder = new Builder();

        foreach ($payload as $name => $value) {
            $builder->withClaim($name, $value);
        }

        $signer = new Sha256();
        $signingKey = InMemory::plainText($secretKey);

        $token = $builder->getToken($signer, $signingKey);

        return $token->toString();
    }

    /**
     * @param string|null $token
     * @param string|null $secretKey
     *
     * @return stdClass
     */
    public function decode(
        $token = null,
        $secretKey = null
    ) {
        if (true === empty($token)) {
            throw new JWTSignatureException(JWTSignatureException::MSG_MISSING_PARAMETER_TOKEN);
        }

        if (true === empty($secretKey)) {
            throw new JWTSignatureException(JWTSignatureException::MSG_MISSING_PARAMETER_SIGNINGKEY);
        }

        $signer = new Sha256();
        $signingKey = InMemory::plainText($secretKey);

        $configuration = Configuration::forSymmetricSigner(
            $signer,
            $signingKey
        );

        $result = null;
        $oClaims = new stdClass();

        try {
            $result = $configuration->parser()->parse($token);
            $claims = $result->claims();
            $objClaims = $claims->all();

            /**
             * the lcobbuci/jwt parser returns a stdClass object but its
             * attributes are not recursively typecasted as stdClass,
             * so we need to 1) cast it to an associative array
             * (by json encoding + json decoding with the associative array parameter set to true)
             * 2) recursively cast its attributes to stdClass.
             */
            $jsonEncodedClaims = json_encode($objClaims);

            if (true !== empty($jsonEncodedClaims)) {
                $oClaims = json_decode($jsonEncodedClaims, true);
            }

            // $oClaims = $this->app->helper->misc->arrayToStdClass($oClaims);
            $oClaims = $this->app->helper->misc->arrayToStdClass($oClaims);
        } catch (UnsupportedHeaderFound $e) {
            throw new JWTSignatureException(JWTSignatureException::MSG_DECODING_TOKEN_IMPOSSIBLE);
        } catch (CannotDecodeContent $e) {
            throw new JWTSignatureException(JWTSignatureException::MSG_DECODING_TOKEN_IMPOSSIBLE);
        } catch (InvalidTokenStructure $e) {
            throw new JWTSignatureException(JWTSignatureException::MSG_DECODING_TOKEN_INVALID_STRUCTURE);
        }

        return $oClaims;
    }

    /**
     * @param string $token
     * @param string $secretKey
     *
     * @return bool
     */
    public function validateSecretKey(
        $token = null,
        $secretKey = null
    ) {
        if (true === empty($token)) {
            throw new JWTSignatureException(JWTSignatureException::MSG_MISSING_PARAMETER_TOKEN);
        }

        if (true === empty($secretKey)) {
            throw new JWTSignatureException(JWTSignatureException::MSG_MISSING_PARAMETER_SIGNINGKEY);
        }

        $signer = new Sha256();
        $signingKey = InMemory::plainText($secretKey);

        $configuration = Configuration::forSymmetricSigner(
            $signer,
            $signingKey
        );

        $constraints = [
            new SignedWith(
                $signer,
                $signingKey
            ),
        ];

        try {
            $oToken = $configuration->parser()->parse($token);
        } catch (UnsupportedHeaderFound $e) {
            throw new JWTSignatureException(JWTSignatureException::MSG_DECODING_TOKEN_IMPOSSIBLE);
        } catch (CannotDecodeContent $e) {
            throw new JWTSignatureException(JWTSignatureException::MSG_DECODING_TOKEN_IMPOSSIBLE);
        } catch (InvalidTokenStructure $e) {
            throw new JWTSignatureException(JWTSignatureException::MSG_DECODING_TOKEN_INVALID_STRUCTURE);
        }

        if (true !== $configuration->validator()->validate($oToken, ...$constraints)) {
            return false;
        }

        return true;
    }
}

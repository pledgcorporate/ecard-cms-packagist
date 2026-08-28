<?php

namespace Ecard\Cms\App;

use Ecard\Cms\Dependencies\Lcobucci\JWT\Builder;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Configuration;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Encoding\CannotDecodeContent;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Signer\Hmac\Sha256;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Signer\Key\InMemory;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Token\InvalidTokenStructure;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Token\UnsupportedHeaderFound;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Validation\Constraint\SignedWith;
use Ecard\Cms\Exception\JWTSignatureException;

class JWTSignature
{
    public function __construct()
    {
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
     * @return array<string, string> $payload
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
        $arrClaims = [];

        try {
            $result = $configuration->parser()->parse($token);
            $claims = $result->claims();
            $objClaims = $claims->all();

            // the lcobbuci/jwt parser returns a stdClass object but we need an associative array
            $jsonEncodedClaims = json_encode($objClaims);

            if (true !== empty($jsonEncodedClaims)) {
                $arrClaims = json_decode($jsonEncodedClaims, true);
            }
        } catch (UnsupportedHeaderFound $e) {
            throw new JWTSignatureException(JWTSignatureException::MSG_DECODING_TOKEN_IMPOSSIBLE);
        } catch (CannotDecodeContent $e) {
            throw new JWTSignatureException(JWTSignatureException::MSG_DECODING_TOKEN_IMPOSSIBLE);
        } catch (InvalidTokenStructure $e) {
            throw new JWTSignatureException(JWTSignatureException::MSG_DECODING_TOKEN_INVALID_STRUCTURE);
        }

        return $arrClaims;
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

<?php

namespace Clinically\PrismBedrock\Schemas\Titan;

use Clinically\PrismBedrock\Contracts\BedrockEmbeddingsHandler;
use Illuminate\Support\Arr;
use Prism\Prism\Embeddings\Request;
use Prism\Prism\Embeddings\Response;
use Prism\Prism\Embeddings\Response as EmbeddingsResponse;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\ValueObjects\Embedding;
use Prism\Prism\ValueObjects\EmbeddingsUsage;
use Prism\Prism\ValueObjects\Meta;
use Throwable;

class TitanEmbeddingsHandler extends BedrockEmbeddingsHandler
{
    /**
     * @throws PrismException
     */
    public function handle(Request $request): Response
    {
        $inputs = $request->inputs();
        if (count($inputs) > 1) {
            throw new PrismException(
                message: 'Titan embeddings only support a single input at a time'
            );
        }

        $payload = [
            'inputText' => Arr::sole($inputs),
            ...$request->providerOptions(),
        ];

        try {
            $response = $this->client->post(
                'invoke',
                $payload,
            );
        } catch (Throwable $e) {
            throw PrismException::providerRequestError($request->model(), $e);
        }

        $response_body = $response->json();

        return new EmbeddingsResponse(
            embeddings: [
                Embedding::fromArray(data_get($response_body, 'embedding', [])),
            ],
            usage: new EmbeddingsUsage(
                tokens: (int) $response->header('X-Amzn-Bedrock-Input-Token-Count'),
            ),
            meta: new Meta(
                id: data_get($response_body, 'id', ''),
                model: $request->model(),
            ),
        );
    }
}

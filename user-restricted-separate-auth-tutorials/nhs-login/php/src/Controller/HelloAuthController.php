<?php
namespace App\Controller;

use App\Service\ISTokenExchange;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Service\JwtHandler;
use App\Service\SeparateAuthHttpClient;

class HelloAuthController extends AbstractController {
    
    #[Route(path: '/', name: 'homepage')]
    public function index(): Response
    {
        return $this->render('hellotut/index.html.twig');
    }


    #[Route('/auth')]
    public function auth()
    {
        $options = [
            'scope' => ['openid']
        ];

        $provider = new \League\OAuth2\Client\Provider\GenericProvider([
            'clientId'                => 'hello-world-tutorials' ,    // The client ID assigned to you by the provider 
            'redirectUri'             => 'http://localhost:8000/hello',
            'urlAuthorize'            => 'https://identity.ptl.api.platform.nhs.uk/auth/realms/NHS-Login-mock-sandbox/protocol/openid-connect/auth',
            'urlAccessToken'          => 'https://identity.ptl.api.platform.nhs.uk/auth/realms/NHS-Login-mock-sandbox/protocol/openid-connect/token',
            'scopes' => ['openid', 'profile'], 
            'urlResourceOwnerDetails' => ''
        ]);
        
        // If we don't have an authorization code then get one
        if (!isset($_GET['code'])) {
            $authorizationUrl = $provider->getAuthorizationUrl($options);                      
            // Redirect the user to the authorization URL.
            header('Location: ' . $authorizationUrl);
            exit;
        }
    }


    #[Route('/hello')]
    public function callback(): Response {   
        
        $code = $_GET['code'];
        $AUTHtokenUrl = 'https://sandbox.api.service.nhs.uk/oauth2-mock/token';

        // FILL THESE VARIABLES
        // Please remove the extension from the file auth.
        $key_path = '/app/auth.key';
        $tokenUrl = 'https://identity.ptl.api.platform.nhs.uk/auth/realms/NHS-Login-mock-sandbox/protocol/openid-connect/token';
        $clientId = 'hello-world-tutorials';

        $APPPrivateKey_path = $_ENV['CLIENT_ID'];
        $APPClientSecret = $_ENV['CLIENT_SECRET'];

        $jwtHandler = new JwtHandler($key_path, $tokenUrl, $clientId, "test-1", 'RS512', 'sha512WithRSAEncryption');
        $jwt = $jwtHandler->GenerateJwt();

        try {

            $separateAuthClient = new SeparateAuthHttpClient($code, $jwt, $tokenUrl);
            $token = $separateAuthClient->getAccessToken();

            if( !isset( $token->id_token) ){
                return $this->redirectToRoute('homepage');
            }
            
            
            $idToken = $token->id_token;
            
    
            
            $ISJwtHandler = new JwtHandler($APPPrivateKey_path, $AUTHtokenUrl, $APPClientSecret, "test-1", 'RS512', 'sha512WithRSAEncryption');
            $ISjwt = $ISJwtHandler->GenerateJwt();
            
     
            $ISTokenExchangeHandler = new ISTokenExchange($idToken, $ISjwt);
            $finalToken = $ISTokenExchangeHandler->tokenExchange();
            
            $provider = new \League\OAuth2\Client\Provider\GenericProvider([
                'urlAuthorize'=> '', 
                'urlAccessToken'=> '', 
                'urlResourceOwnerDetails' => ''
            ]);
            $options['headers']['Content-Type'] = 'application/json';
            $options['headers']['Accept'] = 'application/json';
            $request = $provider->getAuthenticatedRequest( 'GET', 'https://sandbox.api.service.nhs.uk/hello-world/hello/user', $finalToken, $options );
            $response = $provider->getParsedResponse( $request );
            $html_response = implode('' ,$response);

        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
            exit($e->getMessage());
        }

        return $this->render('hellotut/auth.html.twig', [
            'number' => $html_response 
        ]);

    }
   
}


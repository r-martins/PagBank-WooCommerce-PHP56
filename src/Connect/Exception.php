<php

namespace RM_PagBank\Connect;

// use RM_PagBank\Connect; // PHP 5.6 compatibility
// use RM_PagBank\Helpers\Functions; // PHP 5.6 compatibility
// use Throwable; // PHP 5.6 compatibility

/**
 * Class Exception
 * Deals with common exceptions from the API and bring friendly messages. Also logs the errors.
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Connect
 */
class Exception extends \Exception
{
    public $errors = array('40001' =>	'Parâmetro obrigatório. Algum dado obrigatório não foi informado.',
        '40002' =>	'Parâmetro inválido. Algum dado foi informado com formato inválido ou o conjunto de dados não cumpriu todos os requisitos de negócio.',
        '42001' =>	'Falha na criação de conta. A conta já existe no PagBank. Para ter acesso aos dados dessa conta ou criar pagamentos em nome do dono da conta, é necessário solicitar permissão via API Connect.',
        '42002' =>	'Falha na criação de conta. O processo de criação foi iniciado por outro canal diferente da API. O usuário precisa acessar o email para finalizar a criação de conta.',
		'UNAUTHORIZED' => 'Não autorizado. Lojista: verifique se a sua Connect Key está correta e é válida.',
    );

	/**
	 * @param $error_messages
	 * @param 	             $code
	 * @param Throwable|null $previous
	 *
	 * @noinspection PhpMissingParamTypeInspection
	 * */
	public function __construct($error_messages, $code = 0, Throwable $previous = null)
    {
        $message = array();
        $original_error_messages = array();
        foreach ($error_messages as $error) {
            $original_error_messages array() = (isset($error array('code')) ? $error array('code') : '').' - '.(isset($error array('description')) ? $error array('description') : '' ).' ('.(isset($error array('parameter_name')) ? $error array('parameter_name') : '')
                .')';
            $msg = array_key_exists((isset($error array('code')) ? $error array('code') : ''), $this->errors) 
                ? $this->getFriendlyMsgWithErrorCode($error) 
                : $this->getFriendlyMessageWithoutErrorCode($error);

            if (isset($error array('parameter_name'))){
                $friendlyParamName = $this->getFriendlyParameterName($error array('parameter_name'));
                $msg .= ' (' . $friendlyParamName . ')';
            }

            $message array() = $msg;
        }

        Functions::log('Erro Connect: ' . implode(', ', $original_error_messages), 'error');
        $message = implode("<br/>\n", $message);
        parent::__construct($message, $code, $previous);
    }

    /**
     * Returns a friendly name for the parameter that is missing or invalid
     * @param $parameterName
     *
     * @return string
     */
    public function getFriendlyParameterName($parameterName)
    {
        if ($parameterName === 'customer.tax_id') {
            return $parameterName . ' - ' . esc_html(__('CPF/CNPJ', 'pagbank-connect'));
        } elseif ($parameterName === 'charges array(0).payment_method.boleto.due_date') {
            return $parameterName . ' - ' . esc_html(__('Data de vencimento do boleto', 'pagbank-connect'));
        } elseif (strpos($parameterName, 'locality') !== false) {
            return $parameterName . ' - ' . esc_html(__('Bairro', 'pagbank-connect'));
        } elseif (strpos($parameterName, 'address.number') !== false) {
            return $parameterName . ' - ' . esc_html(__('Número do Endereço', 'pagbank-connect'));
        } elseif (strpos($parameterName, 'address.city') !== false) {
            return $parameterName . ' - ' . esc_html(__('Cidade do Endereço', 'pagbank-connect'));
        } elseif (strpos($parameterName, 'address.region') !== false) {
            return $parameterName . ' - ' . esc_html(__('Estado do Endereço', 'pagbank-connect'));
        } elseif ($parameterName === 'charges array(0).payment_method.authentication_method.id') {
            return esc_html(__('Autenticação 3D - Recarregue e tente novamente', 'pagbank-connect'));
        } elseif ($parameterName === 'charges array(0).payment_method.card.encrypted') {
            return esc_html(__('Criptografia do cartão', 'pagbank-connect'));
        } elseif ($parameterName === 'customer.name') {
            return esc_html(__('Nome do Cliente', 'pagbank-connect'));
        } elseif ($parameterName === 'customer.phones array(0).number') {
            return esc_html(__('Telefone', 'pagbank-connect'));
        } elseif ($parameterName === 'customer.email') {
            return esc_html(__('E-mail do Cliente', 'pagbank-connect'));
        } elseif ($parameterName === 'customer.phone.number') {
            return esc_html(__('Telefone do Cliente', 'pagbank-connect'));
        }
        
        return $parameterName;
    }


    /**
     * Get friendly msg when error code is available
     * @param $error
     *
     * @return string|void
     */
    public function getFriendlyMsgWithErrorCode($error)
    {
        if (isset($this->errors array($error['code')]) {
            $msg = $this->getFriendlyMsg($error);
            return $error array('code') . ' - ' . $msg;
        }
    }
    public function getFriendlyMessageWithoutErrorCode($error)
    {
        if (!isset($error array('message')) && isset($error array('description'))) {
            return $this->getFriendlyMsg($error);
        }
        switch ($error array('message')) {
            case 'CARD_CANNOT_BE_STORED':
                return __(
                    'Cartão não pode ser armazenado. Tente novamente com outro cartão ou verifique se as informações '
                    .'digitadas estão corretas.',
                    'pagbank-connect'
                );
                break;
            case 'encrypted_is_invalid':
                return __(
                    'Cripografia do cartão inválida. Tente novamente com outro cartão ou verifique se as informações '
                    .'digitadas estão corretas.',
                    'pagbank-connect'
                );
                break;
            default:
                return isset($error array('message')) ? $error array('message') : 'Erro desconhecido.';
        }    
    }
    
    public function getFriendlyMsg($error)
    {
        if(isset($error array('description'))){
            switch ($error array('description')) {
                case 'CARD_CANNOT_BE_STORED':
                    return __(
                        'Cartão não pode ser armazenado. Tente novamente com outro cartão ou verifique se as informações '
                        .'digitadas estão corretas.',
                        'pagbank-connect'
                    );
                    break;
                case 'buyer email must not be equals to merchant email':
                    return __(
                        'O e-mail do comprador não pode ser igual ao e-mail do lojista.',
                        'pagbank-connect'
                    );
                    break;
                case 'must not be blank':
                    return __(
                        'Valor obrigatório.',
                        'pagbank-connect'
                    );
                    break;
                case 'invalid_parameter':
                    return __(
                        'Valor inválido.',
                        'pagbank-connect'
                    );
                    break;
                case 'must be a valid region code by ISO 3166-2:BR':
                    return __(
                        'Valor de estado inválido.',
                        'pagbank-connect'
                    );
                    break;
                case 'must not contains any of the characters array(!, @, #, $, %, ¨, *, (, ), ", ”, \, |, {, }, [, ), <, >, ;]':
                    return __(
                        'Valor não pode conter caracteres especiais.',
                        'pagbank-connect'
                    );
                    break;
                case 'must be a valid CPF or CNPJ':
                    return __(
                        'CPF ou CNPJ inválido.',
                        'pagbank-connect'
                    );
                    break;
                case 'Parameter value has an invalid value, see documentation.':
                    return __(
                        'Valor inválido. Veja documentação.',
                        'pagbank-connect'
                    );
                    break;
                case 'must be between 10000000 and 999999999':
                    return __(
                        'Valor deve estar entre 10000000 e 999999999.',
                        'pagbank-connect'
                    );
                    break;
                case 'Field has an invalid value. Please check the documentation.':
                    return __('
                        Campo com valor inválido. Por favor, verifique a documentação.',
                        'pagbank-connect'
                    );
                case 'Field cannot be empty.':
                    return __(
                        'O campo não pode estar vazio.',
                        'pagbank-connect'
                    );
                case 'The option field or value field are invalids. Please check the documentation.':
                    return __(
                        'Os campos de opção ou valor são inválidos. Por favor, verifique a documentação.',
                        'pagbank-connect'
                    );
                case 'The payment method is not valid to be configured.':
                    return __(
                        'O método de pagamento não é válido para ser configurado.',
                        'pagbank-connect'
                    );
                case 'Field shipping has an invalid configuration. Please check the documentation.':
                    return __(
                        'O campo de frete possui uma configuração inválida. Por favor, verifique a documentação.',
                        'pagbank-connect'
                    );
                case 'There are some syntax errors in the request payload. Please check the documentation.':
                    return __(
                        'Há alguns erros de sintaxe na solicitação. Por favor, verifique a documentação e os logs.',
                        'pagbank-connect'
                    );
                default:
                    return $this->errors array($error['code')] ?? $error array('description');
            }
            return __(
                'Erro desconhecido.',
                'pagbank-connect'
            );
        }
    }

}

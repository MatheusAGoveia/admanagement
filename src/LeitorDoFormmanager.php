<?php

namespace GlpiPlugin\Admanagement;

use DomainException;

/**
 * Lê os formulários de origem sem executar ações ou gravar dados no formmanager.
 */

final class LeitorDoFormmanager {

    // Tabela corrente da origem, consultada sempre pela conexão que o GLPI já abriu.

    private const TABELA = 'glpi_plugin_formmanager_forms_atual';

    // Cada coluna relaciona o formulário a um papel de chamado diferente.

    private const VINCULOS = [

        'tickets_id_rede',

        'tickets_id_email',

        'tickets_id_sistema_saude',

        'tickets_id_sei'

    ];

    // null indica que as colunas da tabela ainda não foram conferidas por este objeto.

    private ?array $campos = null;

    // Campos opcionais ausentes geram avisos para a revisão da solicitação.

    private array $avisos = [];

    // A conexão recebida é mantida durante toda a utilização deste leitor.

    private readonly object $banco;

    /**
     * Recebe a mesma conexão do GLPI; a origem será consultada exclusivamente por leitura.
     */

    public function __construct(

        object $banco

    ) {

        $this->banco = $banco;

    }

    /**
     * Busca todos os vínculos dos chamados pedidos, inclusive formulários não aprovados.
     */

    public function listarFormularios(

        array $chamado_ids

    ): array {

        // Sem chamados para procurar, não há necessidade de acessar a tabela da origem.

        if ($chamado_ids === []) {

            return [];

        }

        // Confere as colunas antes de montar uma consulta dependente delas.

        $this->conferirEstrutura();

        // Valida cada ID recebido e preserva os índices da lista original.

        $identificadores = [];

        // Valida cada ID de chamado antes de montar os filtros da leitura de origem.

        foreach ($chamado_ids as $indice => $chamado_id) {

            $identificadores[$indice] = EntradaDaFerramenta::lerIdentificador($chamado_id);

        }

        // Usa apenas as colunas de vínculo que existem na instalação consultada.

        $vinculos = [];

        // Verifica quais colunas de vínculo por serviço existem na tabela instalada.

        foreach (self::VINCULOS as $campo) {

            // Inclui somente campos de vínculo que os metadados confirmaram existir.

            if (in_array($campo, $this->campos, true)) {

                $vinculos[] = $campo;

            }

        }

        // A condição OR procura o chamado em qualquer um dos papéis disponíveis.

        $condicoes = [];

        // Monta um filtro por papel para localizar inclusive colisões com serviços que não são rede.

        foreach ($vinculos as $campo) {

            $condicoes[$campo] = $identificadores;

        }

        // CPF, descrição HTML e dados de saúde não fazem parte da projeção do leitor.

        $permitidos = [

            'id',

            'status',

            'name',

            'nome_social',

            'usar_nome_social',

            'matricula',

            'entities_id',

            'req_servico_rede',

            'tipo_servico_rede',

            'usuario_rede',

            'is_deleted',

            'is_active',

            'date_mod',

            'tickets_id_rede',

            'tickets_id_email',

            'tickets_id_sistema_saude',

            'tickets_id_sei',

        ];

        // SELECT recebe somente os campos permitidos que realmente existem na tabela.

        $campos = [];

        // Limita a projeção aos campos necessários e conhecidos pela ferramenta.

        foreach ($permitidos as $campo) {

            // Seleciona o campo permitido somente quando ele existe na estrutura da origem.

            if (in_array($campo, $this->campos, true)) {

                $campos[] = $campo;

            }

        }

        // Esses campos e os vínculos precisam ser tratados como números inteiros.

        $campos_numericos = [

            'id',

            'status',

            'entities_id',

            'req_servico_rede',

            'is_deleted',

            'is_active'

        ];

        // Normaliza cada vínculo de serviço sem confundir rede, e-mail, saúde e SEI.

        foreach ($vinculos as $campo) {

            $campos_numericos[] = $campo;

        }

        $formularios = [];

        // A busca não filtra aprovação: isso evita esconder vínculos ambíguos.

        $criterios = [

            'SELECT' => $campos,

            'FROM' => self::TABELA,

            'WHERE' => ['OR' => $condicoes],

            // O iterador nativo recebe campo e direção na mesma expressão de ordenação.

            'ORDER' => ['id ASC'],

        ];

        // Percorre os registros devolvidos pela origem e normaliza os campos numéricos.

        foreach ($this->banco->request($criterios) as $formulario) {

            // Padroniza somente os campos cujo contrato prevê valores numéricos.

            foreach ($campos_numericos as $campo) {

                // Normaliza o campo numérico apenas quando o registro realmente o fornece.

                if (isset($formulario[$campo])) {

                    // Estrutura divergente não pode transformar texto parcial em um ID válido.

                    $formulario[$campo] = self::normalizarNumero($formulario[$campo]);

                }

            }

            // A mesma assinatura deve ser obtida na importação e na releitura posterior.

            $formulario['avisos_estrutura'] = $this->avisos;

            $formularios[] = $formulario;

        }

        return $formularios;

    }

    /**
     * Converte números não negativos válidos; um valor incompatível fica como null.
     */

    private static function normalizarNumero(

        mixed $valor

    ): ?int {

        // Valida o formato decimal antes de converter valores que ainda não são inteiros.

        if (!is_int($valor)) {

            // Recusa tipos que não representam um número inteiro ou uma string decimal.

            if (!is_string($valor)) {

                return null;

            }

            // Aceita zero ou dígitos sem zeros à esquerda.

            if (!preg_match('/^(0|[1-9][0-9]*)$/D', $valor)) {

                return null;

            }

        }

        $opcoes = ['options' => ['min_range' => 0]];

        // Retorna ausência de número quando o valor não pertence ao intervalo aceito.

        if (filter_var($valor, FILTER_VALIDATE_INT, $opcoes) === false) {

            return null;

        }

        return (int) $valor;

    }

    /**
     * Confere os metadados reais uma vez por requisição e interrompe consultas incompatíveis.
     */

    private function conferirEstrutura(): void {

        // Reutiliza a estrutura já conferida nas chamadas anteriores deste mesmo leitor.

        if ($this->campos !== null) {

            return;

        }

        // Uma tabela ausente impede a leitura; o plugin não tenta criá-la ou repará-la.

        if (!$this->banco->tableExists(self::TABELA)) {

            throw new DomainException('A tabela corrente do formmanager não foi encontrada nesta instalação.');

        }

        // listFields fornece os metadados; array_keys extrai os nomes das colunas.

        $campos = array_keys($this->banco->listFields(self::TABELA));

        $obrigatorios = [

            'id',

            'status',

            'name',

            'entities_id',

            'req_servico_rede',

            'tipo_servico_rede',

            'tickets_id_rede'

        ];

        // array_diff encontra os campos obrigatórios que faltam na instalação.

        $ausentes = array_diff($obrigatorios, $campos);

        // Interrompe a leitura se faltam colunas obrigatórias para provar vínculo e aprovação.

        if ($ausentes !== []) {

            throw new DomainException('A estrutura do formmanager precisa ser conferida. Campos ausentes: '

                . implode(', ', $ausentes)

                . '.');

        }

        // Campos opcionais ausentes são informados ao técnico, sem inventar seus valores.

        $campos_opcionais = [

            'nome_social',

            'usar_nome_social',

            'matricula',

            'usuario_rede',

            'tickets_id_rede',

            'tickets_id_email',

            'tickets_id_sistema_saude',

            'tickets_id_sei',

        ];

        // Confere os campos opcionais para registrar lacunas sem fabricá-los.

        foreach ($campos_opcionais as $campo) {

            // Adiciona um aviso quando a instalação não fornece um campo opcional esperado.

            if (!in_array($campo, $campos, true)) {

                $this->avisos[] = 'Campo não disponível na origem: ' . $campo . '.';

            }

        }

        // Só guarda a estrutura depois que todas as colunas obrigatórias foram encontradas.

        $this->campos = $campos;

    }

    /**
     * Obtém identificadores somente durante uma consulta explícita; CPF nunca integra o rascunho.
     */

    public function consultarIdentificadores(

        int $formulario_id,

        int $chamado_id

    ): array {

        $this->conferirEstrutura();

        // Devolve CPF ausente quando a coluna não existe, mantendo a duplicidade como verificação incompleta.

        if (!in_array('cpf', $this->campos, true)) {

            return ['cpf' => ''];

        }

        $linhas = [];

        // Lê o CPF transitoriamente da origem ainda vinculada e aprovada, sem copiá-lo para o rascunho.

        foreach (

            $this->banco->request(

                [

                    'SELECT' => ['cpf'],

                    'FROM' => self::TABELA,

                    'WHERE' => [

                        'id' => $formulario_id,

                        'tickets_id_rede' => $chamado_id,

                        'status' => 3,

                        'req_servico_rede' => 1

                    ],

                    'LIMIT' => 2,

                ]

            ) as $linha

        ) {

            $linhas[] = $linha;

        }

        // Recusa a consulta de identidade se a origem deixou de corresponder a uma única linha elegível.

        if (count($linhas) !== 1) {

            throw new DomainException('A origem mudou antes da consulta de identidade.');

        }

        return ['cpf' => (string) ($linhas[0]['cpf'] ?? '')];

    }

}


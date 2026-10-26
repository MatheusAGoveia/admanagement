<?php

namespace GlpiPlugin\Admanagement;

use InvalidArgumentException;

/**
 * Configura somente leitura LDAP; senhas continuam no ambiente do servidor.
 */

final class ConfiguracaoDoDiretorio {

    /**
     * Devolve conexões próprias cadastradas; lista vazia significa integração não configurada.
     */

    public static function consultar(): array {

        return ConfiguracaoOperacional::consultar('diretorios', []);

    }

    /**
     * Aceita somente dados de conexão TLS e nomes de variáveis, nunca o valor de uma senha.
     */

    public static function salvar( array $entrada ): void {

        $dominio = [];

        // Valida e limita os textos administrativos antes de formar a conexão de leitura.

        foreach (

            [

                'nome' => 120,

                'host' => 255,

                'base_dn' => 500,

                'bind_env' => 100,

                'senha_env' => 100,

                'atributo_cpf' => 80,

                'atributo_matricula' => 80

            ] as $campo => $limite

        ) {

            $dominio[$campo] = EntradaDaFerramenta::lerTexto($entrada[$campo] ?? '', $limite);

        }

        // Confere a presença dos parâmetros indispensáveis da conexão e das referências de credencial.

        foreach ([

            'nome',

            'host',

            'base_dn',

            'bind_env',

            'senha_env'

        ] as $campo) {

            // Recusa salvar uma conexão que não possui todos os parâmetros obrigatórios.

            if ($dominio[$campo] === '') {

                throw new InvalidArgumentException('Preencha nome, servidor, Base DN e as referências das credenciais.');

            }

        }

        // Aceita apenas um nome DNS como servidor, sem esquema ou caminho inserido no campo.

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*$/iD', $dominio['host'])) {

            throw new InvalidArgumentException('Informe somente o nome DNS do servidor.');

        }

        // Valida os nomes das variáveis de ambiente usadas para obter bind e senha transitoriamente.

        foreach ([

            'bind_env',

            'senha_env'

        ] as $campo) {

            // Recusa referências de credenciais fora do formato permitido para nomes de variáveis.

            if (!preg_match('/^[A-Z_][A-Z0-9_]*$/iD', $dominio[$campo])) {

                throw new InvalidArgumentException('Referência de credencial inválida.');

            }

        }

        // Confere os nomes opcionais dos atributos LDAP de CPF e matrícula.

        foreach ([

            'atributo_cpf',

            'atributo_matricula'

        ] as $campo) {

            // Rejeita nomes de atributo que não podem ser usados na projeção LDAP configurada.

            if (

                $dominio[$campo] !== ''

                && !preg_match('/^[a-z][a-z0-9-]*$/iD', $dominio[$campo])

            ) {

                throw new InvalidArgumentException('Nome de atributo LDAP inválido.');

            }

        }

        $dominio['seguranca'] = $entrada['seguranca'] ?? 'LDAPS';

        // Recusa conexão sem um dos modos TLS admitidos pelo leitor.

        if (!in_array($dominio['seguranca'], [

            'LDAPS',

            'STARTTLS'

        ], true)) {

            throw new InvalidArgumentException('A conexão precisa usar LDAPS ou STARTTLS.');

        }

        $dominio['porta'] = EntradaDaFerramenta::lerIdentificador($entrada['porta'] ?? '636');

        // Rejeita uma porta fora do intervalo técnico permitido.

        if ($dominio['porta'] > 65535) {

            throw new InvalidArgumentException('Porta inválida.');

        }

        $dominio['obrigatorio'] = isset($entrada['obrigatorio']);

        $dominios = self::consultar();

        // O identificador é técnico e estável; mudar a conexão exige novas consultas/revisões.

        $id = isset($entrada['dominio_id']) && $entrada['dominio_id'] !== ''

            ? EntradaDaFerramenta::lerIdentificador($entrada['dominio_id'])

            : 1 + max(array_merge([0], array_map('intval', array_keys($dominios))));

        $dominios[$id] = $dominio;

        ConfiguracaoOperacional::salvar('diretorios', $dominios);

    }

    /**
     * Resolve somente uma conexão explicitamente cadastrada pelo administrador.
     */

    public static function obter( int $id ): array {

        $dominios = self::consultar();

        // Recusa selecionar um domínio que não está explicitamente cadastrado.

        if (!isset($dominios[$id])) {

            throw new InvalidArgumentException('O domínio não está configurado.');

        }

        return $dominios[$id];

    }

}


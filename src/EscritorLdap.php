<?php

namespace GlpiPlugin\Admanagement;

use DomainException;

/** Limita escritas aos atributos confirmados, com alvo GUID e comparação do valor anterior. */
final class EscritorLdap extends ConexaoLdap {

    /** Acrescenta CPF apenas em atributo simples vazio, preservando estado e validade da conta. */
    public function preencherCpf(array $conta, string $cpf): void {
        $atributo = $this->dominio['atributo_cpf'] ?? '';
        // A validação é repetida na fronteira de escrita, mesmo depois da consulta ao esquema.
        if ($atributo === '' || trim($conta['cpf']) !== '' || in_array(strtolower($atributo), ['office', 'physicaldeliveryofficename'], true)) {
            throw new DomainException('O CPF não está vazio ou o atributo de destino não foi homologado.');
        }
        $mudancas = self::troca('userAccountControl', $conta['controle'], $conta['controle']);
        $mudancas = array_merge($mudancas, self::troca('accountExpires', $conta['validade'], $conta['validade']));
        $mudancas[] = ['attrib' => $atributo, 'modtype' => LDAP_MODIFY_BATCH_ADD, 'values' => [IdentidadeDoDiretorio::normalizarCpf($cpf)]];
        $this->enviar($conta['guid'], $mudancas);
    }

    /** Atualiza a validade sem reativar uma conta que mudou de estado depois da consulta. */
    public function renovar(array $conta, string $validade): void {
        $mudancas = array_merge(self::troca('accountExpires', $conta['validade'], $validade), self::troca('userAccountControl', $conta['controle'], $conta['controle']));
        $this->enviar($conta['guid'], $mudancas);
    }

    /** Acrescenta somente ACCOUNTDISABLE, mantendo todos os demais indicadores da conta. */
    public function bloquear(array $conta): void {
        $novo = (string) ((int) $conta['controle'] | 2);
        $mudancas = array_merge(self::troca('userAccountControl', $conta['controle'], $novo), self::troca('accountExpires', $conta['validade'], $conta['validade']));
        $this->enviar($conta['guid'], $mudancas);
    }

    /** Remove o valor esperado e adiciona o novo na mesma modificação atômica LDAP. */
    public static function troca(string $atributo, string $anterior, string $novo): array {
        return [
            ['attrib' => $atributo, 'modtype' => LDAP_MODIFY_BATCH_REMOVE, 'values' => [$anterior]],
            ['attrib' => $atributo, 'modtype' => LDAP_MODIFY_BATCH_ADD, 'values' => [$novo]],
        ];
    }

    /** Envia um único Modify por GUID; falhas exigem releitura em vez de tentativa automática. */
    private function enviar(string $guid, array $mudancas): void {
        // Não recebe DN ou instruções LDAP diretamente do formulário.
        if (!preg_match('/^[a-f0-9]{32}$/D', $guid)) {
            throw new DomainException('O identificador estável da conta não foi confirmado.');
        }
        // Sem permissive-modify, a remoção de um valor concorrente diferente deve falhar.
        if (!@ldap_modify_batch($this->conexao, '<GUID=' . $guid . '>', $mudancas)) {
            throw new DomainException('Alteração LDAP não confirmada. Consulte a conta e reconcilie antes de tentar novamente.');
        }
    }
}

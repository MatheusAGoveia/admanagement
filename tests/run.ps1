# Executa cada suíte em processo próprio, sem carregar o GLPI nem conectar ao AD.

param(

    [string]$ExecutavelPhp = 'php'

)

$ErrorActionPreference = 'Stop'

$raizDosTestes = $PSScriptRoot

# Resolve o executável uma vez para evitar depender de aliases ao chamar cada suíte.

$ExecutavelPhp = (Get-Command -Name $ExecutavelPhp -CommandType Application -ErrorAction Stop).Source

# Percorre todas as suítes independentes, incluindo a cobertura dos comentários do próprio código.

foreach (

    $suite in @(

        'acesso',

        'carregamento',

        'refatoracao',

        'fluxo',

        'fluxo_perfis',

        'regras_http',

        'regras_vigentes',

        'concorrencia',

        'visibilidade',

        'consultas_nativas',

        'documentacao'

    )

) {

    & $ExecutavelPhp (Join-Path $raizDosTestes ($suite + '.php'))

    # Interrompe a validação assim que uma suíte sinaliza falha, preservando seu código de saída.

    if ($LASTEXITCODE -ne 0) {

        exit $LASTEXITCODE

    }

}

Write-Output 'Todas as onze suítes passaram.'

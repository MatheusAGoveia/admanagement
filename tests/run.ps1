# Executa cada suíte em processo próprio, sem carregar o GLPI nem conectar ao AD.

param(

    [string]$ExecutavelPhp = 'php'

)

$ErrorActionPreference = 'Stop'

$raizDosTestes = $PSScriptRoot

# Percorre todas as suítes independentes, incluindo a cobertura dos comentários do próprio código.

foreach (

    $suite in @(

        'acesso',

        'carregamento',

        'refatoracao',

        'fluxo',

        'regras_http',

        'regras_vigentes',

        'concorrencia',

        'visibilidade',

        'documentacao'

    )

) {

    & $ExecutavelPhp (Join-Path $raizDosTestes ($suite + '.php'))

    # Interrompe a validação assim que uma suíte sinaliza falha, preservando seu código de saída.

    if ($LASTEXITCODE -ne 0) {

        exit $LASTEXITCODE

    }

}

Write-Output 'Todas as nove suítes passaram.'

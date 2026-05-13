#!/bin/bash

# 🚀 Script de Publicação do CDN Laravel SDK no Packagist
# Execute: chmod +x publish.sh && ./publish.sh

set -e

echo "🚀 Preparando SDK para publicação no Packagist..."

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Função para print colorido
print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️ $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️ $1${NC}"
}

# Verificar se estamos no diretório correto
if [ ! -f "composer.json" ]; then
    print_error "composer.json não encontrado. Execute o script dentro do diretório cdn-sdk."
    exit 1
fi

# Verificar se o git está configurado
if ! git rev-parse --git-dir > /dev/null 2>&1; then
    print_error "Este não é um repositório Git. Initialize com: git init"
    exit 1
fi

print_info "Validando composer.json..."
composer validate
if [ $? -eq 0 ]; then
    print_status "composer.json válido!"
else
    print_error "composer.json inválido. Corrija os erros antes de continuar."
    exit 1
fi

# Instalar dependências
print_info "Instalando dependências..."
composer install --no-dev --optimize-autoloader

# Executar testes se existirem
if [ -d "tests" ] && [ -f "phpunit.xml" ]; then
    print_info "Executando testes..."
    ./vendor/bin/phpunit
    if [ $? -ne 0 ]; then
        print_error "Testes falharam. Corrija antes de publicar."
        exit 1
    fi
    print_status "Todos os testes passaram!"
fi

# Verificar se há mudanças não commitadas
if [ -n "$(git status --porcelain)" ]; then
    print_warning "Existem mudanças não commitadas:"
    git status --short
    echo
    read -p "Deseja commitá-las automaticamente? (y/N): " auto_commit

    if [[ $auto_commit =~ ^[Yy]$ ]]; then
        git add .
        read -p "Mensagem do commit: " commit_message
        if [ -z "$commit_message" ]; then
            commit_message="chore: prepare SDK for release"
        fi
        git commit -m "$commit_message"
        print_status "Mudanças commitadas!"
    else
        print_error "Commit as mudanças manualmente antes de continuar."
        exit 1
    fi
fi

# Perguntar pela versão
current_version=$(git describe --tags --abbrev=0 2>/dev/null || echo "v0.0.0")
print_info "Versão atual: $current_version"
echo

echo "Escolha o tipo de release:"
echo "1) Patch (1.0.0 → 1.0.1) - Bug fixes"
echo "2) Minor (1.0.0 → 1.1.0) - New features"
echo "3) Major (1.0.0 → 2.0.0) - Breaking changes"
echo "4) Custom version"

read -p "Opção (1-4): " version_choice

case $version_choice in
    1)
        new_version=$(echo $current_version | awk -F. '{$NF = $NF + 1;} 1' | sed 's/ /./g')
        ;;
    2)
        new_version=$(echo $current_version | awk -F. '{$(NF-1) = $(NF-1) + 1; $NF = 0} 1' | sed 's/ /./g')
        ;;
    3)
        new_version=$(echo $current_version | awk -F. '{$(NF-2) = $(NF-2) + 1; $(NF-1) = 0; $NF = 0} 1' | sed 's/ /./g')
        ;;
    4)
        read -p "Digite a nova versão (ex: v1.2.3): " new_version
        ;;
    *)
        print_error "Opção inválida."
        exit 1
        ;;
esac

# Verificar formato da versão
if [[ ! $new_version =~ ^v?[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    print_error "Formato de versão inválido. Use: v1.2.3"
    exit 1
fi

# Adicionar v se não tiver
if [[ ! $new_version =~ ^v ]]; then
    new_version="v$new_version"
fi

print_info "Nova versão será: $new_version"
read -p "Confirmar? (y/N): " confirm

if [[ ! $confirm =~ ^[Yy]$ ]]; then
    print_error "Publicação cancelada."
    exit 1
fi

# Criar tag de release
print_info "Criando tag $new_version..."
git tag -a $new_version -m "Release $new_version"

# Push para o repositório
print_info "Fazendo push das mudanças e tags..."
git push origin main
git push origin $new_version

print_status "SDK preparado com sucesso!"

echo
print_info "📋 Próximos passos manuais:"
echo "1. Acesse https://packagist.org"
echo "2. Faça login/cadastro"
echo "3. Clique em 'Submit'"
echo "4. Cole a URL do repositório Git"
echo "5. Configure auto-update webhook (recomendado)"

echo
print_info "📋 URLs importantes:"
echo "• Packagist: https://packagist.org/packages/submit"
echo "• Documentação: https://packagist.org/about"
echo "• GitHub Webhook: Settings → Webhooks → Add webhook"
echo "  URL: https://packagist.org/api/github?token=YOUR_TOKEN"

echo
print_status "🎉 Release $new_version criada com sucesso!"
print_info "Verifique se tudo está correto no repositório antes de publicar no Packagist."
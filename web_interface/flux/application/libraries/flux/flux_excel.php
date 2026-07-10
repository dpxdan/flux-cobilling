<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Biblioteca minimalista para leitura de arquivos XLSX (XML Spreadsheet).
 * Focada em extrair dados de colunas específicas para o Co-Billing NFCom.
 */
class flux_excel {

    /**
     * Extrai dados de um arquivo XLSX e retorna um array de abas.
     * @param string $filepath Caminho completo do arquivo .xlsx
     * @return array ['sheet_name' => [rows]]
     * @throws Exception
     */
    public function parse_xlsx($filepath) {
        if (!file_exists($filepath)) {
            throw new Exception("Arquivo não encontrado.");
        }

        $zip = new ZipArchive();
        if ($zip->open($filepath) !== TRUE) {
            throw new Exception("Não foi possível abrir o arquivo XLSX (formato Zip inválido).");
        }

        // 1. Carregar strings compartilhadas
        $sharedStrings = [];
        if (($index = $zip->locateName('xl/sharedStrings.xml')) !== false) {
            $data = $zip->getFromIndex($index);
            $xml = simplexml_load_string($data);
            foreach ($xml->si as $si) {
                $sharedStrings[] = (string) ($si->t ?? $si->r->t ?? '');
            }
        }

        // 2. Mapear nomes de abas para IDs de arquivos
        $sheets = [];
        if (($index = $zip->locateName('xl/workbook.xml')) !== false) {
            $data = $zip->getFromIndex($index);
            $xml = simplexml_load_string($data);
            foreach ($xml->sheets->sheet as $s) {
                $sheets[(string)$s['name']] = (string)$s['sheetId'];
            }
        }

        $allData = [];
        foreach ($sheets as $name => $id) {
            $filename = 'xl/worksheets/sheet' . $id . '.xml';
            if (($index = $zip->locateName($filename)) !== false) {
                $data = $zip->getFromIndex($index);
                $xml = simplexml_load_string($data);
                
                $sheetRows = [];
                foreach ($xml->sheetData->row as $row) {
                    $rowData = [];
                    foreach ($row->c as $c) {
                        $val = (string) $c->v;
                        $type = (string) $c['t'];
                        if ($type == 's') {
                            $val = isset($sharedStrings[$val]) ? $sharedStrings[$val] : $val;
                        }
                        
                        $r = (string) $c['r'];
                        preg_match('/^[A-Z]+/', $r, $matches);
                        $col = $matches[0];
                        $rowData[$col] = $val;
                    }
                    $sheetRows[] = $rowData;
                }
                $allData[$name] = $sheetRows;
            }
        }
        $zip->close();

        return $allData;
    }

    /**
     * Converte os dados extraídos do Excel (Cabeçalho e Itens) para o Payload NFCom.
     * 
     * @param array $allSheets Dados de todas as abas
     * @param array $conta     Dados da conta (emitente)
     * @return array Payload para Emissor62
     */
    public function convert_to_payload($allSheets, $conta) {
        $cabecalho = $allSheets['Cabeçalho'] ?? $allSheets['Cabecalho'] ?? [];
        $itensSheet = $allSheets['Itens'] ?? [];

        if (empty($cabecalho)) throw new Exception("Aba 'Cabeçalho' não encontrada ou vazia.");

        // Mapeamento vertical da aba Cabeçalho (Coluna B contém os valores)
        $map = [];
        foreach ($cabecalho as $row) {
            if (isset($row['A']) && isset($row['B'])) {
                $map[trim($row['A'])] = trim($row['B']);
            }
        }

        // Identificação
        $payload = [
            'Identificacao' => [
                'Ambiente'       => (int) ($map['Ambiente'] ?? 2),
                'Serie'          => (int) ($map['Série'] ?? 1),
                'Numero'         => (int) ($map['Número'] ?? 0),
                'DHEmissao'      => $map['Data Emissão'] ?? date('c'),
                'Emissao'        => (int) ($map['Tipo Emissão'] ?? 1),
                'SiteAutorizador'=> (int) ($map['Site Autorizador'] ?? 0),
                'Finalidade'     => (int) ($map['Finalidade'] ?? 0),
                'Faturamento'    => (int) ($map['Tipo Faturamento'] ?? 0),
                'CessaoMeiosRede'=> (int) ($map['Cessão Meios Rede'] ?? 0)
            ],
            'Destinatario' => [
                'Nome'      => $map['Nome Destinatário'] ?? '',
                'Doc'       => preg_replace('/\D/', '', $map['Doc Destinatário'] ?? ''),
                'DocOutro'  => '',
                'IndIEDest' => (int) ($map['IndIEDest'] ?? 1),
                'ie'        => preg_replace('/\D/', '', $map['IE Destinatário'] ?? ''),
                'IM'        => '',
                'Endereco'  => [
                    'Logradouro'    => $map['Logradouro'] ?? '',
                    'Numero'        => $map['Número End'] ?? 'S/N',
                    'Complemento'   => $map['Complemento'] ?? '',
                    'Bairro'        => $map['Bairro'] ?? '',
                    'CodMunicipio'  => $map['Cod Município'] ?? '',
                    'NomeMunicipio' => $map['Nome Município'] ?? '',
                    'CEP'           => preg_replace('/\D/', '', $map['CEP'] ?? ''),
                    'UF'            => $map['UF Dest'] ?? '',
                    'CodPais'       => '1058',
                    'NomePais'      => 'BRASIL',
                    'Fone'          => preg_replace('/\D/', '', $map['Telefone'] ?? '')
                ]
            ],
            'Assinante' => [
                'Codigo'            => $map['Código Assinante'] ?? '',
                'Tipo'              => (int) ($map['Tipo Assinante'] ?? 1),
                'Servico'           => (int) ($map['Tipo Serviço'] ?? 1),
                'Contrato'          => $map['Número Contrato'] ?? '',
                'ContratoIni'       => $map['Início Contrato'] ?? '',
                'TerminalPrincipal' => [
                    'Numero' => preg_replace('/\D/', '', $map['Número Terminal'] ?? ''),
                    'UF'     => $map['UF Terminal'] ?? ''
                ]
            ],
            'Itens' => [],
            'Faturamento' => [
                'Fatura' => [
                    'Competencia' => $map['Competência'] ?? date('Ym'),
                    'Vencimento'  => $map['Vencimento'] ?? '',
                    'PeriodoUso'  => [
                        'Inicio' => $map['Período Início'] ?? '',
                        'Final'  => $map['Período Fim'] ?? ''
                    ],
                    'CodBarras'        => $map['Cód Barras'] ?? '1',
                    'DebitoAutomatico' => '',
                    'Banco'            => null,
                    'EnderecoFat'      => null,
                    'Pix'              => null
                ],
                'Central'       => null,
                'Cofaturamento' => null
            ],
            'Autorizacoes' => [],
            'Informacoes'  => $map['Informações Adicionais'] ?? ''
        ];

        // Autorizações (CNPJ)
        if (!empty($map['Autorização Doc'])) {
            $payload['Autorizacoes'][] = ['Doc' => preg_replace('/\D/', '', $map['Autorização Doc'])];
        }

        // Itens (Mapeamento Horizontal)
        if (!empty($itensSheet)) {
            // Pula cabeçalho da aba Itens (Linha 1)
            $itemData = array_slice($itensSheet, 1);
            foreach ($itemData as $row) {
                if (empty($row['A'])) continue; // Pula se código do produto vazio

                $payload['Itens'][] = [
                    'Produto' => [
                        'Codigo'        => (string) ($row['A'] ?? ''),
                        'Nome'          => (string) ($row['B'] ?? ''),
                        'Classificacao' => (int) ($row['C'] ?? 0),
                        'CFOP'          => (int) ($row['D'] ?? 0),
                        'UnidadeMedida' => (int) ($row['E'] ?? 1),
                        'Quantidade'    => (float) ($row['F'] ?? 0),
                        'ValorItem'     => (float) ($row['G'] ?? 0),
                        'ValorDesconto' => (float) ($row['H'] ?? 0),
                        'IndDevolucao'  => (int) ($row['I'] ?? 0)
                    ],
                    'Imposto' => [
                        'IndSemCST' => 0,
                        'ICMS' => [
                            'CST'            => (string) ($row['J'] ?? '00'),
                            'Aliquota'       => (float) ($row['K'] ?? 0),
                            'AliquotaFCP'    => (float) ($row['L'] ?? 0),
                            'AliquotaRed'    => (float) ($row['M'] ?? 0),
                            'AliquotaUFDest' => (float) ($row['N'] ?? 0),
                            'ValorDeson'     => (float) ($row['O'] ?? 0),
                            'CodBeneficio'   => (string) ($row['P'] ?? '')
                        ],
                        'PIS' => [
                            'CST'      => (string) ($row['Q'] ?? '01'),
                            'Aliquota' => (float) ($row['R'] ?? 0)
                        ],
                        'COFINS' => [
                            'CST'      => (string) ($row['S'] ?? '01'),
                            'Aliquota' => (float) ($row['T'] ?? 0)
                        ]
                    ]
                ];
            }
        }

        return $payload;
    }
}

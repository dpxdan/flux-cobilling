<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Biblioteca minimalista para leitura de arquivos XLSX (XML Spreadsheet).
 * Focada em extrair dados de colunas específicas para o Co-Billing NFCom.
 */
class flux_excel {

    /**
     * Extrai dados de um arquivo XLSX e retorna um array de linhas.
     * @param string $filepath Caminho completo do arquivo .xlsx
     * @return array
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

        // 1. Carregar strings compartilhadas (Shared Strings)
        $sharedStrings = [];
        if (($index = $zip->locateName('xl/sharedStrings.xml')) !== false) {
            $data = $zip->getFromIndex($index);
            $xml = simplexml_load_string($data);
            foreach ($xml->si as $si) {
                $sharedStrings[] = (string) $si->t;
            }
        }

        // 2. Carregar a primeira planilha (Sheet1)
        $sheetData = [];
        if (($index = $zip->locateName('xl/worksheets/sheet1.xml')) !== false) {
            $data = $zip->getFromIndex($index);
            $xml = simplexml_load_string($data);
            
            foreach ($xml->sheetData->row as $row) {
                $rowData = [];
                foreach ($row->c as $c) {
                    $val = (string) $c->v;
                    $type = (string) $c['t'];

                    if ($type == 's') { // Shared String
                        $val = isset($sharedStrings[$val]) ? $sharedStrings[$val] : $val;
                    }
                    
                    // Identificar a coluna (A, B, C...)
                    $r = (string) $c['r'];
                    preg_match('/^[A-Z]+/', $r, $matches);
                    $col = $matches[0];
                    $rowData[$col] = $val;
                }
                $sheetData[] = $rowData;
            }
        }
        $zip->close();

        return $sheetData;
    }

    /**
     * Converte os dados extraídos do Excel para o formato de Payload NFCom.
     * Assume uma estrutura de colunas pré-definida ou tenta mapear.
     * @param array $rows
     * @param array $conta Dados da conta para preencher o emitente
     * @return array Payload para Emissor62
     */
    public function convert_to_payload($rows, $conta) {
        // TODO: Implementar mapeamento de colunas do Excel para o Payload
        // Por enquanto, vamos retornar um esqueleto baseado na primeira linha de dados
        if (empty($rows)) throw new Exception("Excel vazio.");

        // Exemplo de mapeamento (ajustar conforme necessidade do Daniel)
        // Coluna A: Chave Co-faturamento
        // Coluna B: Valor, etc...
        
        // Se precisar de uma implementação real, o Daniel deve fornecer o layout do Excel.
        // Como ele pediu apenas para "permitir o envio", vamos focar na infraestrutura.
        
        return []; 
    }
}

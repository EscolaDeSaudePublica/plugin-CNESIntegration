<?php

namespace CNESIntegration\Services;

use CNESIntegration\Repositories\EstabelecimentoDWRepository;
use MapasCulturais\App;
use MapasCulturais\Types\GeoPoint;

class EstabelecimentoService
{

    public function atualizarSpaces()
    {
        ini_set('display_errors', true);
        ini_set('max_execution_time', 0);
        set_time_limit(60000);
        error_reporting(E_ALL);

        $app = App::i();

        $userAdmin = $app->repo('User')->findOneBy(['email' => 'desenvolvimento@esp.ce.gov.br']);
        $userCnes = $app->repo('User')->findOneBy(['email' => 'cnes@esp.ce.gov.br']);

        $app->user = $userAdmin;
        $app->auth->authenticatedUser = $userAdmin;

        $app->em->getConnection()->getConfiguration()->setSQLLogger(null);

        $spaceRepository = new EstabelecimentoDWRepository();
        // retorna uma lista com todos os cnes da base do CNES
        $estabelecimentos = $spaceRepository->getAllEstabelecimentos();

        $cont = 1;
        foreach ($estabelecimentos as $estabelecimento) {
            // retorna todos os dados da view estabelecimentos de um determinado cnes
            $spaceMeta = $app->repo('SpaceMeta')->findOneBy(['value' => $estabelecimento['co_cnes']]);

            if ($estabelecimento["nu_longitude"] == null || $estabelecimento["nu_longitude"] == 'nan') {
                $geo = new GeoPoint(0.0, 0.0);
            } else {
                $geo = new GeoPoint(str_replace(",",".",$estabelecimento["nu_longitude"]), str_replace(",",".",$estabelecimento["nu_latitude"]));
            }

            $nomeFantasia = $estabelecimento["no_fantasia"];
            $razaoSocial =  $estabelecimento["no_razao_social"];
            $tipoUnidade = $estabelecimento['description'];
            $telefone = $estabelecimento["nu_telefone"];
            $percenteAoSus = $estabelecimento['atende_sus'];

            $cep = $estabelecimento['co_cep'];
            $logradouro = $estabelecimento['no_logradouro'];
            $numero = $estabelecimento['nu_endereco'];
            $bairro = $estabelecimento['no_bairro'];
            $municipio = $estabelecimento['municipio'];
            $cnes = $estabelecimento['co_cnes'];
            $now = date('Y-m-d H:i:s');
            $dateTime = new \DateTime($now);

            $competencia = substr_replace($estabelecimento['competencia'], '-', -2, -2);
            $competenciaArray = explode('-', $competencia);
            $competenciaData = $competenciaArray[1] . '/' . $competenciaArray[0];

            $servicosEstabelecimento = $spaceRepository->getServicosPorEstabelecimento($cnes);

            $servicosArray = [];
            foreach ($servicosEstabelecimento as $serv) {
                if (!empty($serv['ds_servico_especializado']) && $serv['ds_servico_especializado'] != 'nan') {
                    $servicosArray[] = $serv['ds_servico_especializado'];
                }
            }

            $tipoUnidadeComAcento = $this->adicionarAcentos($tipoUnidade);
            $term = $app->repo('Term')->findOneBy(['term' => $tipoUnidadeComAcento]);
            if (empty($term)) {
                $term = new \MapasCulturais\Entities\Term;
                $term->taxonomy = 'instituicao_tipos_unidades';
                $term->term = $tipoUnidadeComAcento;
                $term->save(true);
            }

            if ($spaceMeta) {
                $msg = "Atualizando dados do espaço com o CNES  {$cnes} <br>";
                $app->log->debug($msg);
                echo $msg;
                $space = $spaceMeta->owner;
            } else {
                $msg = "Criado um novo espaço com o CNES  {$cnes} <br>";
                $app->log->debug($msg);
                echo $msg;
                $space = new \MapasCulturais\Entities\Space;
            }
            
            
            $space->setLocation($geo);
            $space->name = $nomeFantasia;
            $space->shortDescription = 'CNES: ' . $cnes;
            $space->longDescription = $razaoSocial;
            $space->createTimestamp = $dateTime;
            $space->status = 1;
            $space->ownerId = $userCnes->id;
            $space->is_verified = false;
            $space->public = false;
            $space->type = $this->retornaIdTipoEstabelecimentoPorNome($tipoUnidade);


            if (!empty($cep)) {
                $space->setMetadata('En_CEP', $cep);
            }

            if (!empty($logradouro)) {
                $space->setMetadata('En_Nome_Logradouro', $logradouro);
            }

            if (!empty($numero)) {
                $space->setMetadata('En_Num', $numero);
            }

            if (!empty($bairro)) {
                $space->setMetadata('En_Bairro', $bairro);
            }

            if (!empty($municipio)) {
                $space->setMetadata('En_Municipio', $municipio);
            }
            $space->setMetadata('En_Estado', 'CE');

            if (!empty($cnes)) {
                $space->setMetadata('instituicao_cnes', $cnes);
            }

            $space->setMetadata('instituicao_cnes_data_atualizacao', $now);

            if (!empty($competenciaData)) {
                $space->setMetadata('instituicao_cnes_competencia', $competenciaData);
            }

            if (!empty($tipoUnidade)) {
                $space->setMetadata('instituicao_tipos_unidades', $tipoUnidade);
            }

            if (!empty($telefone)) {
                $space->setMetadata('telefonePublico', $telefone);
            }

            if (!empty($percenteAoSus) && $percenteAoSus != 'nan') {
                $space->setMetadata('instituicao_pertence_sus', $percenteAoSus);
            }

            if (is_array($servicosArray)) {
                $space->setMetadata('instituicao_servicos', implode(', ', $servicosArray));
            }

            $space->save();
            if (($cont % 50) === 0) {
                $space->save(true); // Executes all updates.
                $app->em->clear(); // Detaches all objects from Doctrine!
                $msg = "Dados salvos com sucesso ! - ¨¨\_(* _ *)_/¨¨";
                $app->log->debug($msg);
                echo $msg;
            }
            $cont++;   
        }
        $space->save(true);
        $app->em->clear();
        $msg = "¨¨\_(* _ *)_/¨¨ -  Processo de atualização dos espaços finalizado !  -  ¨¨\_(* _ *)_/¨¨";
        $app->log->debug($msg);
        echo $msg;
    }

    private function adicionarAcentos($frase)
    {
        $arrayComAcento = ['ORGÃOS', 'CAPTAÇÃO', 'NOTIFICAÇÃO', 'PÚBLICA', 'LABORATÓRIO', 'GESTÃO', 'ATENÇÃO', 'BÁSICA', 'DOENÇA', 'CRÔNICA', 'FAMÍLIA',  'ESTRATÉGIA', 'COMUNITÁRIOS', 'LOGÍSTICA',  'IMUNOBIOLÓGICOS', 'REGULAÇÃO', 'AÇÕES', 'SERVIÇOS', 'SERVIÇO', 'HANSENÍASE', 'MÓVEL', 'URGÊNCIAS', 'DIAGNÓSTICO', 'LABORATÓRIO', 'CLÍNICO', 'DISPENSAÇÃO', 'ÓRTESES', 'PRÓTESES', 'REABILITAÇÃO', 'PRÁTICAS', 'URGÊNCIA', 'EMERGÊNCIA', 'VIGILÂNCIA', 'BIOLÓGICOS', 'FARMÁCIA', 'GRÁFICOS', 'DINÂMICOS', 'MÉTODOS', 'PATOLÓGICA', 'INTERMEDIÁRIOS', 'TORÁCICA', 'PRÉ-NATAL', 'IMUNIZAÇÃO', 'CONSULTÓRIO', 'VIOLÊNCIA', 'SITUAÇÃO', 'POPULAÇÕES', 'INDÍGENAS', 'ASSISTÊNCIA', 'COMISSÕES', 'COMITÊS', 'SAÚDE', 'BÁSICA', 'ÁREA', 'PRÉ-HOSPITALAR', 'NÍVEL'];

        $arraySemAcento = ['ORGAOS', 'CAPTACAO', 'NOTIFICACAO', 'PUBLICA', 'LABORATORIO', 'GESTAO', 'ATENCAO', 'BASICA', 'DOENCA', 'CRONICA', 'FAMILIA', 'ESTRATEGIA', 'COMUNITARIOS', 'LOGISTICA',  'IMUNOBIOLOGICOS', 'REGULACAO', 'ACOES', 'SERVICOS', 'SERVICO', 'HANSENIASE', 'MOVEL', 'URGENCIAS', 'DIAGNOSTICO', 'LABORATORIO', 'CLINICO', 'DISPENSACAO', 'ORTESES', 'PROTESES', 'REABILITACAO', 'PRATICAS', 'URGENCIA', 'EMERGENCIA', 'VIGILANCIA', 'BIOLOGICOS', 'FARMACIA', 'GRAFICOS', 'DINAMICOS', 'METODOS', 'PATOLOGICA', 'INTERMEDIARIOS', 'TORACICA', 'PRE-NATAL', 'IMUNIZACAO', 'CONSULTORIO', 'VIOLENCIA', 'SITUACAO', 'POPULACOES', 'INDIGENAS', 'ASSISTENCIA', 'COMISSOES', 'COMITES', 'SAUDE', 'BASICA', 'AREA', 'PRE-HOSPITALAR', 'NIVEL'];

        return str_replace($arraySemAcento, $arrayComAcento, $frase);
    }

    private function salvarSelos($conMap, $idSpace, $idAgent)
    {

        $sql = "SELECT MAX(id)+1 FROM public.seal_relation";
        $maxSealRelation = $conMap->query($sql);
        $id = $maxSealRelation->fetchColumn();

        $id = !empty($id) ? $id : 1;

        $dataHora = date('Y-m-d H:i:s');
        $sqlInsertSeal = "INSERT INTO public.seal_relation 
                    (id, seal_id, object_id, create_timestamp, status, object_type, agent_id, validate_date, renovation_request) 
                    VALUES ({$id} ,'2', '" . $idSpace . "', '{$dataHora}' , '1' , 'MapasCulturais\Entities\Space' , {$idAgent},
                    '2029-12-08 00:00:00' , true)";
        $conMap->exec($sqlInsertSeal);
    }

    private function retornaIdTipoEstabelecimentoPorNome($tipoNome)
    {
        $app = App::i();
        $conn = $app->em->getConnection();
        $tipoNome = $this->adicionarAcentos($tipoNome);

        $sql = "SELECT id FROM public.term WHERE taxonomy='instituicao_tipos_unidades' AND term='{$tipoNome}'";
        $result = $conn->query($sql);
        $id = $result->fetchColumn();
        return $id;
    }
}
<?php

/*
|--------------------------------------------------------------------------
| Mapeamento da planilha "Patrimônios CEAD NOVEMBRO DE 2024" (tarefa 2.12)
|--------------------------------------------------------------------------
|
| Este arquivo diz como cada texto da planilha vira um dado do sistema. Ele
| fica separado do código para poder ser ajustado sem mexer na lógica da
| carga. Depois de mudar algo aqui, rode a carga com --simular e confira o
| relatório antes de gravar.
|
| As comparações ignoram maiúsculas, acentos e espaços repetidos. Por isso
| as chaves abaixo estão em minúsculas e sem acento.
|
| Pendências que afetam este arquivo (página 01, seção 6):
|   - PD09: a planilha não diz o andar de nenhuma sala. Todas estão em
|     "Andar a definir" até alguém informar o andar certo em 'salas'.
|   - PD03: conversão do estado da aba Principal, proposta no relatório de
|     mudanças da tabela bem, seção 6.3, ainda a confirmar.
*/

return [

    /*
    | Andares e a ordem deles no relatório. Toda sala precisa apontar para
    | um andar desta lista.
    */
    'andares' => [
        'Andar a definir' => 0,
    ],

    /*
    | Salas do sistema: nome => andar. Os nomes juntam o número da sala,
    | quando a planilha informa, com o uso dela.
    */
    'salas' => [
        'Sala 01 - Videoconferência' => 'Andar a definir',
        'Sala 02 - Estúdio de vídeo' => 'Andar a definir',
        'Sala 03 - Sala de reunião' => 'Andar a definir',
        'Podcast' => 'Andar a definir',
        'Sala 05 - Suporte TI' => 'Andar a definir',
        'Sala 06 - Laboratório de Informática' => 'Andar a definir',
        'Logística' => 'Andar a definir',
        'Sala 07 - LED' => 'Andar a definir',
        'Sala 08 - DTE' => 'Andar a definir',
        'Sala 11 - Coordenação pedagógica' => 'Andar a definir',
        'Sala 14' => 'Andar a definir',
        'Secretaria UAB' => 'Andar a definir',
        'Coordenação UAB' => 'Andar a definir',
        'Coordenação de projetos' => 'Andar a definir',
        'Monitoramento' => 'Andar a definir',
        'Administrativo' => 'Andar a definir',
        'Diretoria CEAD' => 'Andar a definir',
        'Almoxarifado' => 'Andar a definir',
        'Recepção' => 'Andar a definir',
        'Sala de equipamentos' => 'Andar a definir',
        'Corredor' => 'Andar a definir',
        'Corredor das salas' => 'Andar a definir',
        'Corredor em frente à diretoria' => 'Andar a definir',
    ],

    /*
    | Aba Principal: texto da coluna "Local do patrimônio" => sala do sistema.
    | "Sala 06 - Logistica" e "Laboratório de Informática" ficaram separadas
    | porque a aba de sala chama a sala 06 de laboratório. A confirmar.
    */
    'locais_principal' => [
        'videoconferencia' => 'Sala 01 - Videoconferência',
        'estudio de video' => 'Sala 02 - Estúdio de vídeo',
        'sala de reuniao' => 'Sala 03 - Sala de reunião',
        'sala 05 - suporte ti' => 'Sala 05 - Suporte TI',
        'sala 05' => 'Sala 05 - Suporte TI',
        'laboratorio de informatica' => 'Sala 06 - Laboratório de Informática',
        'sala 06 - logistica' => 'Logística',
        'sala 07' => 'Sala 07 - LED',
        'dte - sala 08' => 'Sala 08 - DTE',
        'sala 11 coordenacao pedagogica' => 'Sala 11 - Coordenação pedagógica',
        'sala 14' => 'Sala 14',
        'secretaria' => 'Secretaria UAB',
        'coordenacao de projetos' => 'Coordenação de projetos',
        'administrativo' => 'Administrativo',
        'diretoria cead' => 'Diretoria CEAD',
        'almoxarifado' => 'Almoxarifado',
        'recepcao' => 'Recepção',
        'sala de equipamentos' => 'Sala de equipamentos',
        'corredor das salas' => 'Corredor das salas',
        'corredor em frente a diretoria' => 'Corredor em frente à diretoria',
    ],

    /*
    | Abas de sala: nome da aba => sala do sistema. A coluna "Local" dessas
    | abas quase sempre vem vazia; vale o nome da aba.
    */
    'abas_de_sala' => [
        'videoconferencia sala 01' => 'Sala 01 - Videoconferência',
        'estudio de video sala 02' => 'Sala 02 - Estúdio de vídeo',
        'sala de reuniao sala 03' => 'Sala 03 - Sala de reunião',
        'podcast' => 'Podcast',
        'suporte ti sala 5' => 'Sala 05 - Suporte TI',
        'laboratorio sala 06' => 'Sala 06 - Laboratório de Informática',
        'led sala 07' => 'Sala 07 - LED',
        'dte sala 08' => 'Sala 08 - DTE',
        'secretaria uab' => 'Secretaria UAB',
        'monitoramento' => 'Monitoramento',
        'administrativo' => 'Administrativo',
        'cordenacao uab' => 'Coordenação UAB',
        'corredor' => 'Corredor',
        'diretoria cead' => 'Diretoria CEAD',
        'almoxarifado' => 'Almoxarifado',
        'coordenacao de projetos' => 'Coordenação de projetos',
    ],

    /* Abas que não são de bens e são ignoradas. */
    'abas_ignoradas' => ['principal', 'grupos'],

    /*
    | Tipos: nome do tipo => começos de descrição que levam a ele. Vence o
    | começo mais longo, então "caixa de som" ganha de "caixa". O resto da
    | descrição, sem a marca e a origem, vira o campo descricao do bem.
    */
    'tipos' => [
        'Ar condicionado' => ['ar condicionado', 'ar-condicionado'],
        'Amplificador' => ['amplificador'],
        'Aparador' => ['aparador'],
        'Armário' => ['armario'],
        'Arquivo' => ['arquivo'],
        'Bancada' => ['bancada'],
        'Bateria' => ['bateria'],
        'Bebedouro' => ['bebedouro', 'filtro de agua'],
        'Cadeira' => ['cadeira', 'longarina', 'assento'],
        'Caixa de som' => ['caixa de som', 'som ambiente'],
        'Câmera' => ['camera', 'camara', 'filmadora'],
        'Carteira escolar' => ['carteira'],
        'Computador' => ['computador', 'computado', 'cpu', 'gabinete'],
        'Equipamento de áudio' => ['dispositivo de som', 'interface de audio', 'microfone', 'mesa de som', 'modulo de som'],
        'Equipamento de videoconferência' => ['dispositivo polycom', 'multipoint', 'unidade de conferencia', 'video counication', 'video communication', 'terminal de comunicacao', 'codec'],
        'Escada' => ['escada'],
        'Estabilizador' => ['estabilizador', 'estabiizador'],
        'Estante' => ['estante', 'prateleira'],
        'Fone de ouvido' => ['fone', 'headset'],
        'Fonte de alimentação' => ['fonte'],
        'Gaveteiro' => ['gaveteiro', 'gaveteteiro', 'gavetao'],
        'Hub' => ['hub'],
        'Iluminação' => ['luz'],
        'Impressora' => ['impressora'],
        'Mesa' => ['mesa', 'escrivaninha'],
        'Monitor' => ['monitor'],
        'Mouse' => ['mouse'],
        'Nobreak' => ['nobreak', 'nobreack', 'no break', 'no-break'],
        'Notebook' => ['notebook'],
        'Óculos 3D' => ['oculos 3d'],
        'Projetor' => ['projetor', 'data-show', 'data show', 'datashow'],
        'Quadro' => ['quadro', 'lousa'],
        'Rack' => ['rack', 'hack'],
        'Reprodutor de DVD' => ['dvd'],
        'Roteador' => ['roteador'],
        'Scanner' => ['scanner', 'scam', 'hp scam'],
        'Servidor' => ['servidor', 'xen server'],
        'Sistema de alarme' => ['sistema de alarme'],
        'Storage' => ['storage'],
        'Suporte' => ['suporte'],
        'Switch' => ['switch'],
        'Teclado' => ['teclado', 'tecaldo'],
        'Telefone' => ['ramal', 'telefone', 'fax'],
        'Tela de projeção' => ['tela'],
        'Televisão' => ['televisao', 'tv'],
        'Tripé' => ['tripe'],
        'Ventilador' => ['ventilador'],
        'Webcam' => ['webcam'],
    ],

    /*
    | Marcas reconhecidas na descrição: como aparece => como fica no sistema.
    | A que aparecer vai para o campo marca e sai da descrição.
    */
    'marcas' => [
        'thermaltake' => 'Thermaltake',
        'hp' => 'HP',
        'lenovo' => 'Lenovo',
        'aoc' => 'AOC',
        'sony' => 'Sony',
        'canon' => 'Canon',
        'lg' => 'LG',
        'samsung' => 'Samsung',
        'carrier' => 'Carrier',
        'york' => 'York',
        'midea' => 'Midea',
        'springer' => 'Springer',
        'electrolux' => 'Electrolux',
        'eletrolux' => 'Electrolux',
        'logitech' => 'Logitech',
        'logi' => 'Logitech',
        'edifier' => 'Edifier',
        'panasonic' => 'Panasonic',
        'apc' => 'APC',
        'positivo' => 'Positivo',
        'dell' => 'Dell',
        'epson' => 'Epson',
        'polycom' => 'Polycom',
        'intelbras' => 'Intelbras',
        'philips' => 'Philips',
        'multilaser' => 'Multilaser',
        'ambient homeco' => 'Ambient Homeco',
        'oneal' => 'Oneal',
    ],

    /*
    | Origem: a planilha escreve o mesmo convênio de vários jeitos ("Convenio
    | mct x sects", "Conveio MCT X SECTES"...). Trecho que aparece => como
    | fica no sistema. Sem nenhum desses trechos, fica só "Convênio".
    */
    'origens' => [
        'mct' => 'Convênio MCT x SECTES',
        'e-tec' => 'Convênio E-TEC',
    ],

    /*
    | Aba Principal: estado => [situação de uso, estado de conservação].
    | Proposta da seção 6.3 do relatório de mudanças da tabela bem.
    | Estado que não está aqui faz a linha ser rejeitada, para não inventar
    | informação; o relatório da carga mostra qual foi.
    */
    'estados_principal' => [
        'ok' => ['em_uso', 'indeterminado'],
        'ok (funciona)' => ['em_uso', 'indeterminado'],
        'em uso' => ['em_uso', 'indeterminado'],
        'usavel' => ['em_uso', 'indeterminado'],
        'utilizavel' => ['em_uso', 'indeterminado'],
        'funcionando' => ['em_uso', 'indeterminado'],
        'funciona' => ['em_uso', 'indeterminado'],
        'usado' => ['em_uso', 'indeterminado'],
        'novo' => ['em_uso', 'novo'],
        'novo ok' => ['em_uso', 'novo'],
        'nova ok' => ['em_uso', 'novo'],
        'sem uso' => ['sem_uso', 'indeterminado'],
        'sem uso / funciona' => ['sem_uso', 'indeterminado'],
        'avarias' => ['em_uso', 'regular'],
        'torta' => ['em_uso', 'regular'],
        'estofado ruim' => ['em_uso', 'regular'],
        'estofado rasgado' => ['em_uso', 'regular'],
        'sem puxador' => ['em_uso', 'regular'],
        'puxador quebrado e amassado' => ['em_uso', 'regular'],
        'uma porta ruim' => ['em_uso', 'regular'],
        'sem 1 dos encosto de braco' => ['em_uso', 'regular'],
        'gaveta de baixo sem fundo e emperrada' => ['em_uso', 'regular'],
        'impressao ruim' => ['em_uso', 'regular'],
        'defeito' => ['sem_uso', 'pessimo'],
        'com defeito' => ['sem_uso', 'pessimo'],
        'estragado' => ['sem_uso', 'pessimo'],
        'quebrada' => ['sem_uso', 'pessimo'],
        'nao funciona' => ['sem_uso', 'pessimo'],
        'sucata' => ['sem_uso', 'sucata'],
        'ok (nao sei se funciona)' => ['em_uso', 'indeterminado'],
        'ok(nao sei se funciona)' => ['em_uso', 'indeterminado'],
        'ok (nao sei e funciona)' => ['em_uso', 'indeterminado'],
        'nao sei se funciona' => ['em_uso', 'indeterminado'],
        'talvez nao funcione' => ['em_uso', 'indeterminado'],
        'mais ou menos' => ['em_uso', 'indeterminado'],
        'meio usavel' => ['em_uso', 'indeterminado'],
    ],

    /*
    | Abas de sala: o estado já é de conservação. A situação de uso não está
    | na planilha e vem da opção --situacao-abas (padrão em_uso), exceto
    | sucata, que entra sem uso. Estado vazio entra indeterminado.
    */
    'estados_abas' => [
        'novo' => 'novo',
        'bom' => 'bom',
        'regular' => 'regular',
        'pessimo' => 'pessimo',
        'sucata' => 'sucata',
        '' => 'indeterminado',
    ],

    /*
    | Textos da coluna Patrimônio que significam "sem plaqueta". O bem entra
    | sem número e com o código interno do sistema (PD07).
    */
    'sem_plaqueta' => ['sem patrimonio', 'sem patrimonio.', 's/ patrimonio', 's/patrimonio', 'nao tem', ''],
];

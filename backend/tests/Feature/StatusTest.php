<?php

test('a rota de status informa que a API e o banco estão no ar', function () {
    $response = $this->getJson('/api/status');

    $response->assertOk()
        ->assertJsonPath('banco.conectado', true)
        ->assertJsonStructure(['aplicacao', 'ambiente', 'banco' => ['conectado', 'conexao', 'nome'], 'horario']);
});

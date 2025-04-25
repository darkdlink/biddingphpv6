<?php
// app/Http/Controllers/DocumentController.php
namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DocumentController extends Controller
{
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'file' => 'required|file|max:10240', // 10MB máximo
            'documentable_id' => 'required|integer',
            'documentable_type' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $file = $request->file('file');
        $path = $file->store('documents', 'public');

        Document::create([
            'name' => $request->name,
            'file_path' => $path,
            'file_type' => $file->getClientOriginalExtension(),
            'documentable_id' => $request->documentable_id,
            'documentable_type' => $request->documentable_type,
        ]);

        return redirect()->back()
            ->with('success', 'Documento enviado com sucesso!');
    }

    public function destroy($id)
    {
        $document = Document::findOrFail($id);

        // Remover arquivo do disco
        if (Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return redirect()->back()
            ->with('success', 'Documento removido com sucesso!');
    }

    public function show($id)
{
    $document = Document::findOrFail($id);

    $filePath = storage_path('app/public/' . $document->file_path);

    if (!file_exists($filePath)) {
        abort(404, 'Arquivo não encontrado.');
    }

    $mimeType = mime_content_type($filePath);

    // Verificar se é um tipo de arquivo que pode ser exibido no navegador
    $viewableMimeTypes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'text/plain',
        'text/html'
    ];

    if (in_array($mimeType, $viewableMimeTypes)) {
        return response()->file($filePath);
    } else {
        // Se não for um tipo de arquivo visualizável, forçar o download
        return response()->download($filePath, $document->name);
    }
}

    // Adicionar método para baixar um documento
    public function download($id)
    {
        $document = Document::findOrFail($id);

        $filePath = storage_path('app/public/' . $document->file_path);

        if (!file_exists($filePath)) {
            abort(404, 'Arquivo não encontrado.');
        }

        return response()->download($filePath, $document->name);
    }
}

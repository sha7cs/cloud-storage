<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

use App\Http\Requests\StoreDepartmentStorageRequest;
use App\Http\Requests\UpdateDepartmentStorageRequest;
use App\Models\DepartmentStorage;
use App\Models\FileType;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use App\Services\VirusTotalService;
use App\Notifications\FileUploaded;
use App\Notifications\FileDeleted;




class DepartmentStorageController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        
        $currentUserDepartment = auth()->user()->Depatrment_id ;
        $categories = Category::where('department_id', $currentUserDepartment)->get();
        $departmentStorages = DepartmentStorage::where('department_id', $currentUserDepartment)->get();
        $fileTypes = FileType::all();
        
        return view('dashboard.layouts.uploadFile',[
            'categories' => $categories,
            'departmentStorages' => $departmentStorages,
            'fileTypes' => $fileTypes,
        ]);
    }

    public function store(StoreDepartmentStorageRequest $request)
    {
        $validatedData = $request->validated();
        $fileType = FileType::find($validatedData['file_type']);
        $folderName = $this->getFolderName($fileType->type);
        $file = $request->file('file');
        
        // Check file size
        $this->checkFileSize($fileType->type, $file);
        
        // Check total file size
        if ($this->checkTotalFileSize(auth()->id(), auth()->user()->Department_id, $fileType, $file->getSize())) {
            // Store the file temporarily
            $filePath = $file->store('temp', 'local');
            
            // Scan and handle the file
            $result = $this->scanAndHandleFile(storage_path("app/{$filePath}"), $file, $fileType, $folderName, $request);
            
            if ($result['status'] === 'success') {
                flash()->success('The file is saved successfully!!');
                return redirect(route('upload-file'));
            } else {
                flash()->error($result['message']);
                return redirect(route('upload-file'));
            }
        } else {
            flash()->error('You have reached the storage limit. File not saved.');
            return redirect(route('upload-file'));
        }
    }
    
    private function scanAndHandleFile($filePath, $file, $fileType, $folderName, $request)
{
    $virusTotalService = app(VirusTotalService::class);
    $scanResponse = $virusTotalService->scanFile($filePath);

    \Log::info('VirusTotal Scan Response: ', $scanResponse); //Logs the response from VirusTotal

    if (isset($scanResponse['response_code']) && $scanResponse['response_code'] == 1) { //VirusTotal returns a response_code of 1 when a scan is successfully initiated.
        $reportResponse = $virusTotalService->getReport($scanResponse['resource']);

        \Log::info('VirusTotal Report Response: ', $reportResponse); //Logs the report response

        if (isset($reportResponse['positives'])) { //positives key indicates the number of antivirus
            if ($reportResponse['positives'] == 0) { //no threats were detected.
                // Move file to the final storage
                $finalPath = $file->store("department_storage/{$folderName}", 'local');
                //report for the file using the hash of the file
                $this->createDepartmentStorage($request, $fileType, $finalPath, $file->getSize());

                // Confirmation
                $user = $request->user();
                $message = 'A new file has been uploaded by user ' . $user->name;
                // Notify department admins
                $user->notifyDepartmentAdmins($message);
                // Notify the user
                $user->notify(new FileUploaded('Your file has been successfully uploaded.'));

                return ['status' => 'success'];
            } else {
                // Handle file detected as malicious
                return ['status' => 'error', 'message' => 'The file is flagged as malicious by VirusTotal.'];
            }
        } else {
            // Handle cases where 'positives' key is not set
            $errorMessage = isset($reportResponse['verbose_msg']) ? $reportResponse['verbose_msg'] : 'The file scan result is not available.';
            return ['status' => 'error', 'message' => $errorMessage];
        }
    } else {
        // Handle error cases
        $errorMessage = isset($scanResponse['verbose_msg']) ? $scanResponse['verbose_msg'] : 'Error scanning the file with VirusTotal.';
        return ['status' => 'error', 'message' => $errorMessage];
    }
}


    





    protected function getFolderName($fileType)
    {
        return strtolower($fileType);
    }

    protected function checkFileSize($fileType, $file)
    {
        $maxFileSize = $this->getMaxFileSize($fileType);
        if ($file->getSize() > $maxFileSize * 1024 * 1024) {
            return redirect(route('upload-file'));
        }
    }

    protected function checkTotalFileSize($userId, $departmentId, $fileType, $fileSize)
    {
        $user = User::findOrFail($userId);
        $totalFileSize = $this->getUserTotalFileSize($userId, $departmentId);
    
        if ($totalFileSize + $fileSize > $user->storage_size * 1024 * 1024) { // Convert to bytes
            return false;
        }
    
        return true;
    }

    protected function createDepartmentStorage($request, $fileType, $filePath, $fileSize)
    {
        $user = User::findOrFail(auth()->id());
        DepartmentStorage::create([
            'title' => $request->title,
            'department_id' => auth()->user()->Depatrment_id,
            'user_id' => auth()->id(),
            'category_id' => $request->category_id,
            'file_type' => $fileType->id,
            'file' => $filePath,
            'file_size' => $fileSize,
            'description' => $request->description,
            'storage_size' => $user->storage_size,
        ]);
    }

    private function getUserTotalFileSize($userId, $departmentId)
    {
        return DepartmentStorage::where('user_id', $userId)
            ->where('department_id', $departmentId)
            ->sum('file_size');
    }
    private function getMaxFileSize($fileType)
    {
        $maxFileSizes = [
            'Document' => 2, // 2 MB
            'Powerpoint' => 5, // 5 MB
            'Image' => 5, // 5 MB
            'Video' => 20, // 20 MB
            'PDF' => 5, // 5 MB
        ];

        return $maxFileSizes[$fileType] ?? 2; // Default to 2MB if not found
    }

    public function showfile()
    {
        $currentUserDepartment = auth()->user()->Depatrment_id ;
        $departmentStorages = DepartmentStorage::where('department_id', $currentUserDepartment)
                            ->where('user_id', auth()->id())
                            ->get();
        $userName = auth()->user()->name;

        
        if (auth()->user()->role_id == 1) {
            return view('dashboard.layouts.showfile')
            ->with('departmentStorages', $departmentStorages)
            ->with('userName', $userName);
        } else {
            return view('dashboard.layouts.showfile')
            ->with('departmentStorages', $departmentStorages)
            ->with('userName', $userName);
        }
    }
    
    public function downloadFile($id)
    {
        $item = DepartmentStorage::findOrFail($id);
        $filePath = Storage::disk('local')->path($item->file);
        $file = Storage::disk('local')->get($item->file);
    
        return response($file, 200)
            ->header('Content-Type', mime_content_type($filePath))
            ->header('Content-Disposition', 'attachment; filename="' . basename($filePath) . '"');
    }

    /**
     * Display the specified resource.
     */
   

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $storage = DepartmentStorage::findOrfail($id);
        $fileTypes = FileType::all();

        return view('dashboard.layouts.edit-file')
        ->with([
            'storage' => $storage,
            'fileTypes' => $fileTypes,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDepartmentStorageRequest $request, DepartmentStorage $departmentStorage)
    {
        $validatedData = $request->validated();

        // dd( $validatedData);
        $fileType = FileType::find($validatedData['file_type']);
        $departmentId = auth()->user()->Depatrment_id;
        
        // dd($request->all(), $departmentId,$fileType);
        $departmentStorage = DepartmentStorage::find($request->id);
        $departmentStorage->update([
            'title'=>$request->title,
            'department_id' => $departmentId,
            'user_id' => auth()->id(),
            'category_id' => $request->category_id,
            'file_type' => $fileType->id,
            'description'=> $request->description,
        ]);

        flash()->success('file "'.$request->title.'" has been updated');
        return redirect(route('show-file'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $Storagetitle = DepartmentStorage::findOrFail($id)->title;
        $Storage = DepartmentStorage::findOrFail($id)->delete();
        flash()->success('file "'.$Storagetitle.'" has been deleted');
        $user = $request->user();
        $message = 'A file has been deleted by user ' . $user->name;
    
        // Notify department admins
        $user->notifyDepartmentAdminsOnDeletion($message);
        // Notify the user
        $user->notify(new FileDeleted('You have successfully deleted a file.'));


        return back();
    }

}

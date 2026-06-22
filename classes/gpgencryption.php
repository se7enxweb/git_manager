<?php
/**
 * @package GitManager
 * @class   GPGEncryption
 * @author  7x
 * @date    2026-06-21
 **/

class GPGEncryption
{
    private $gpgPath;
    
    public function __construct() {
        $this->gpgPath = '/usr/bin/gpg';
    }
    
    /**
     * Check if GPG is available
     * 
     * @return bool True if GPG is available
     */
    public function isAvailable() {
        return file_exists( $this->gpgPath ) && is_executable( $this->gpgPath );
    }
    
    /**
     * Encrypt a file using symmetric encryption with passphrase
     * 
     * @param string $inputFile Path to input file
     * @param string $passphrase Passphrase for encryption
     * @param bool $deleteOriginal Delete original file after encryption
     * @return array Result with success status and output file path
     */
    public function encryptFile( $inputFile, $passphrase, $deleteOriginal = false ) {
        if( !$this->isAvailable() ) {
            return array(
                'success' => false,
                'message' => 'GPG is not available on this system'
            );
        }
        
        if( !file_exists( $inputFile ) ) {
            return array(
                'success' => false,
                'message' => 'Input file does not exist: ' . $inputFile
            );
        }
        
        if( empty( $passphrase ) ) {
            return array(
                'success' => false,
                'message' => 'Passphrase is required for encryption'
            );
        }
        
        $outputFile = $inputFile . '.gpg';
        
        // Use symmetric encryption with AES256 cipher
        // --batch: non-interactive mode
        // --yes: overwrite existing files without asking
        // --passphrase-fd 0: read passphrase from stdin
        // --cipher-algo AES256: use AES256 encryption
        // -c: symmetric encryption
        $cmd = $this->gpgPath . ' --batch --yes --passphrase-fd 0 --cipher-algo AES256 -c -o ' 
             . escapeshellarg($outputFile) . ' ' . escapeshellarg($inputFile) . ' 2>&1';
        
        // Create a temporary file for the passphrase
        $passFd = array(
            0 => array('pipe', 'r'),  // stdin
            1 => array('pipe', 'w'),  // stdout
            2 => array('pipe', 'w')   // stderr
        );
        
        $process = proc_open($cmd, $passFd, $pipes);
        
        if( !is_resource($process) ) {
            return array(
                'success' => false,
                'message' => 'Failed to start GPG process'
            );
        }
        
        // Write passphrase to stdin
        fwrite($pipes[0], $passphrase . "\n");
        fclose($pipes[0]);
        
        // Read output
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        
        $returnCode = proc_close($process);
        
        if( $returnCode !== 0 ) {
            return array(
                'success' => false,
                'message' => 'GPG encryption failed: ' . $stderr . ' ' . $stdout
            );
        }
        
        if( !file_exists($outputFile) ) {
            return array(
                'success' => false,
                'message' => 'Encrypted file was not created'
            );
        }
        
        // Delete original file if requested
        if( $deleteOriginal ) {
            unlink($inputFile);
        }
        
        return array(
            'success' => true,
            'file' => $outputFile,
            'size' => filesize($outputFile)
        );
    }
    
    /**
     * Decrypt a GPG encrypted file
     * 
     * @param string $inputFile Path to encrypted file
     * @param string $passphrase Passphrase for decryption
     * @param string $outputFile Optional output file path
     * @return array Result with success status and output file path
     */
    public function decryptFile( $inputFile, $passphrase, $outputFile = null ) {
        if( !$this->isAvailable() ) {
            return array(
                'success' => false,
                'message' => 'GPG is not available on this system'
            );
        }
        
        if( !file_exists( $inputFile ) ) {
            return array(
                'success' => false,
                'message' => 'Input file does not exist: ' . $inputFile
            );
        }
        
        if( empty( $passphrase ) ) {
            return array(
                'success' => false,
                'message' => 'Passphrase is required for decryption'
            );
        }
        
        // If output file not specified, remove .gpg extension
        if( $outputFile === null ) {
            if( substr($inputFile, -4) === '.gpg' ) {
                $outputFile = substr($inputFile, 0, -4);
            } else {
                $outputFile = $inputFile . '.decrypted';
            }
        }
        
        // Use symmetric decryption
        $cmd = $this->gpgPath . ' --batch --yes --passphrase-fd 0 -d -o ' 
             . escapeshellarg($outputFile) . ' ' . escapeshellarg($inputFile) . ' 2>&1';
        
        $passFd = array(
            0 => array('pipe', 'r'),  // stdin
            1 => array('pipe', 'w'),  // stdout
            2 => array('pipe', 'w')   // stderr
        );
        
        $process = proc_open($cmd, $passFd, $pipes);
        
        if( !is_resource($process) ) {
            return array(
                'success' => false,
                'message' => 'Failed to start GPG process'
            );
        }
        
        // Write passphrase to stdin
        fwrite($pipes[0], $passphrase . "\n");
        fclose($pipes[0]);
        
        // Read output
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        
        $returnCode = proc_close($process);
        
        if( $returnCode !== 0 ) {
            return array(
                'success' => false,
                'message' => 'GPG decryption failed: ' . $stderr . ' ' . $stdout
            );
        }
        
        if( !file_exists($outputFile) ) {
            return array(
                'success' => false,
                'message' => 'Decrypted file was not created'
            );
        }
        
        return array(
            'success' => true,
            'file' => $outputFile,
            'size' => filesize($outputFile)
        );
    }
    
    /**
     * Encrypt multiple files in a directory
     * 
     * @param string $directory Directory containing files to encrypt
     * @param string $passphrase Passphrase for encryption
     * @param bool $deleteOriginals Delete original files after encryption
     * @return array Result with success status and list of encrypted files
     */
    public function encryptDirectory( $directory, $passphrase, $deleteOriginals = false ) {
        $results = array();
        $errors = array();
        
        if( !is_dir($directory) ) {
            return array(
                'success' => false,
                'message' => 'Directory does not exist: ' . $directory
            );
        }
        
        $files = scandir($directory);
        foreach( $files as $file ) {
            if( $file === '.' || $file === '..' || $file === 'description.txt' ) {
                continue;
            }
            
            $filePath = $directory . '/' . $file;
            if( is_file($filePath) && substr($file, -4) !== '.gpg' ) {
                $result = $this->encryptFile($filePath, $passphrase, $deleteOriginals);
                if( $result['success'] ) {
                    $results[] = basename($result['file']);
                } else {
                    $errors[] = $file . ': ' . $result['message'];
                }
            }
        }
        
        if( !empty($errors) ) {
            return array(
                'success' => false,
                'message' => 'Some files failed to encrypt: ' . implode(', ', $errors),
                'encrypted_files' => $results
            );
        }
        
        return array(
            'success' => true,
            'encrypted_files' => $results
        );
    }
}

?>
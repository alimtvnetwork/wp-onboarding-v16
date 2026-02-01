// Package site - AES-256-GCM encryption for application passwords
package site

import (
	"crypto/aes"
	"crypto/cipher"
	"crypto/rand"
	"crypto/sha256"
	"errors"
	"io"
)

// encrypt encrypts plaintext using AES-256-GCM
func encrypt(plaintext, key []byte) ([]byte, error) {
	// Derive a 32-byte key using SHA-256
	hash := sha256.Sum256(key)
	derivedKey := hash[:]

	block, err := aes.NewCipher(derivedKey)
	if err != nil {
		return nil, err
	}

	gcm, err := cipher.NewGCM(block)
	if err != nil {
		return nil, err
	}

	// Create a nonce
	nonce := make([]byte, gcm.NonceSize())
	if _, err := io.ReadFull(rand.Reader, nonce); err != nil {
		return nil, err
	}

	// Encrypt and prepend nonce
	ciphertext := gcm.Seal(nonce, nonce, plaintext, nil)
	return ciphertext, nil
}

// decrypt decrypts ciphertext using AES-256-GCM
func decrypt(ciphertext, key []byte) ([]byte, error) {
	if len(ciphertext) == 0 {
		return nil, errors.New("empty ciphertext")
	}

	// Derive the same 32-byte key
	hash := sha256.Sum256(key)
	derivedKey := hash[:]

	block, err := aes.NewCipher(derivedKey)
	if err != nil {
		return nil, err
	}

	gcm, err := cipher.NewGCM(block)
	if err != nil {
		return nil, err
	}

	nonceSize := gcm.NonceSize()
	if len(ciphertext) < nonceSize {
		return nil, errors.New("ciphertext too short")
	}

	// Extract nonce and ciphertext
	nonce, ciphertext := ciphertext[:nonceSize], ciphertext[nonceSize:]

	// Decrypt
	plaintext, err := gcm.Open(nil, nonce, ciphertext, nil)
	if err != nil {
		return nil, err
	}

	return plaintext, nil
}

// Package site - AES-256-GCM encryption for application passwords
// Uses shared crypto package for encryption/decryption
package site

import (
	"wp-plugin-publish/internal/crypto"
)

// encrypt encrypts plaintext using AES-256-GCM (wrapper for crypto package)
func encrypt(plaintext, key []byte) ([]byte, error) {
	return crypto.Encrypt(plaintext, key)
}

// decrypt decrypts ciphertext using AES-256-GCM (wrapper for crypto package)
func decrypt(ciphertext, key []byte) ([]byte, error) {
	return crypto.Decrypt(ciphertext, key)
}

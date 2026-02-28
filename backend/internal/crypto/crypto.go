// Package crypto provides shared encryption utilities
package crypto

import (
	"crypto/aes"
	"crypto/cipher"
	"crypto/rand"
	"crypto/sha256"
	"io"

	"wp-plugin-publish/pkg/apperror"
)

// Encrypt encrypts plaintext using AES-256-GCM
func Encrypt(plaintext, key []byte) ([]byte, *apperror.AppError) {
	hash := sha256.Sum256(key)
	derivedKey := hash[:]

	block, err := aes.NewCipher(derivedKey)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrCryptoEncrypt, "create AES cipher")
	}

	gcm, err := cipher.NewGCM(block)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrCryptoEncrypt, "create GCM")
	}

	nonce := make([]byte, gcm.NonceSize())
	if _, err := io.ReadFull(rand.Reader, nonce); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrCryptoEncrypt, "generate nonce")
	}

	ciphertext := gcm.Seal(nonce, nonce, plaintext, nil)
	return ciphertext, nil
}

// Decrypt decrypts ciphertext using AES-256-GCM
func Decrypt(ciphertext, key []byte) ([]byte, *apperror.AppError) {
	if len(ciphertext) == 0 {
		return nil, apperror.New(apperror.ErrCryptoInvalid, "empty ciphertext")
	}

	hash := sha256.Sum256(key)
	derivedKey := hash[:]

	block, err := aes.NewCipher(derivedKey)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrCryptoDecrypt, "create AES cipher")
	}

	gcm, err := cipher.NewGCM(block)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrCryptoDecrypt, "create GCM")
	}

	nonceSize := gcm.NonceSize()
	if len(ciphertext) < nonceSize {
		return nil, apperror.New(apperror.ErrCryptoInvalid, "ciphertext too short")
	}

	nonce, ciphertext := ciphertext[:nonceSize], ciphertext[nonceSize:]

	plaintext, err := gcm.Open(nil, nonce, ciphertext, nil)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrCryptoDecrypt, "GCM decryption failed")
	}

	return plaintext, nil
}
